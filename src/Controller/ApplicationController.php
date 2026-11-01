<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AdoptionApplication;
use App\Entity\AnimalListing;
use App\Entity\ApplicationAnswer;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\ListingStatus;
use App\Form\ApplicationFormType;
use App\Repository\AdoptionApplicationRepository;
use App\Repository\ConversationRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ApplicationController extends AbstractController
{
    #[Route('/annonces/{id<\d+>}/reserver', name: 'app_application_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADOPTANT')]
    public function new(
        AnimalListing $listing,
        Request $request,
        EntityManagerInterface $entityManager,
        AdoptionApplicationRepository $applicationRepository,
        AuditLogger $auditLogger,
        ClockInterface $clock,
        #[Autowire(service: 'limiter.listing_application')]
        RateLimiterFactory $applicationLimiter,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $limiter = $applicationLimiter->create((string) $user->getId());
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Trop de demandes envoyées. Réessayez dans une heure.');

                return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
            }
        }

        if (!$listing->isVisible()) {
            throw $this->createNotFoundException('Cette annonce n\'est plus disponible.');
        }

        if ($listing->isOwnedBy($user)) {
            $this->addFlash('warning', 'Vous ne pouvez pas adopter votre propre animal.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        if (null !== $applicationRepository->findExisting((int) $listing->getId(), (int) $user->getId())) {
            $this->addFlash('info', 'Vous avez déjà une demande en cours pour cet animal.');

            return $this->redirectToRoute('app_application_mine');
        }

        $application = (new AdoptionApplication())
            ->setListing($listing)
            ->setAdoptant($user);

        $form = $this->createForm(ApplicationFormType::class, $application, ['listing' => $listing]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($listing->getQuestions() as $question) {
                $answer = (new ApplicationAnswer())
                    ->setQuestion($question)
                    ->setAnswer(mb_substr(trim(strip_tags((string) $form->get('answer_'.$question->getId())->getData())), 0, 2000));
                $application->addAnswer($answer);
                $entityManager->persist($answer);
            }

            if ($application->isWarningConfirmed() && null === $application->getWarningConfirmedAt()) {
                $application->setWarningConfirmedAt($clock->now());
            }

            $entityManager->persist($application);
            $entityManager->flush();

            $auditLogger->log('application.created', $application, [
                'listingId' => $listing->getId(),
                'handoverDate' => $application->getHandoverDate()?->format('Y-m-d'),
                'warningConfirmed' => $application->isWarningConfirmed(),
            ]);

            $this->addFlash('success', 'Votre demande a été envoyée au cédant.');

            return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
        }

        return $this->render('application/new.html.twig', [
            'listing' => $listing,
            'form' => $form,
        ]);
    }

    #[Route('/mes-demandes', name: 'app_application_mine', methods: ['GET'])]
    #[IsGranted('ROLE_ADOPTANT')]
    public function mine(AdoptionApplicationRepository $repository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('application/mine.html.twig', [
            'applications' => $repository->findByAdoptant((int) $user->getId()),
        ]);
    }

    #[Route('/mes-annonces/demandes', name: 'app_application_received', methods: ['GET'])]
    #[IsGranted('ROLE_CEDANT')]
    public function received(AdoptionApplicationRepository $repository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('application/received.html.twig', [
            'applications' => $repository->findByCedant((int) $user->getId()),
        ]);
    }

    #[Route('/demandes/{id<\d+>}', name: 'app_application_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(AdoptionApplication $application): Response
    {
        $this->denyUnlessParticipant($application);

        return $this->render('application/show.html.twig', [
            'application' => $application,
        ]);
    }

    #[Route('/demandes/{id<\d+>}/annuler', name: 'app_application_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_ADOPTANT')]
    public function cancel(
        AdoptionApplication $application,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ClockInterface $clock,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($application->getAdoptant()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel_application_'.$application->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$application->isActive()) {
            $this->addFlash('warning', 'Cette demande ne peut plus être annulée.');

            return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
        }

        $application->setStatus(ApplicationStatus::Cancelled);
        $application->setDecidedAt($clock->now());
        $application->touch();

        $listing = $application->getListing();
        if (null !== $listing && ListingStatus::Reserved === $listing->getStatus()) {
            $listing->setStatus(ListingStatus::Published);
        }

        $entityManager->flush();
        $auditLogger->log('application.cancelled', $application);
        $this->addFlash('success', 'Votre demande a été annulée.');

        return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
    }

    #[Route('/demandes/{id<\d+>}/accepter', name: 'app_application_approve', methods: ['POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function approve(
        AdoptionApplication $application,
        Request $request,
        EntityManagerInterface $entityManager,
        ConversationRepository $conversationRepository,
        AuditLogger $auditLogger,
        ClockInterface $clock,
    ): Response {
        $listing = $this->denyUnlessListingOwner($application);

        if (!$this->isCsrfTokenValid('approve_application_'.$application->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$application->isPending()) {
            $this->addFlash('warning', 'Cette demande a déjà été traitée.');

            return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
        }

        $application->setStatus(ApplicationStatus::Approved);
        $application->setDecidedAt($clock->now());
        $application->touch();

        foreach ($listing->getApplications() as $other) {
            if ($other->getId() !== $application->getId() && $other->isPending()) {
                $other->setStatus(ApplicationStatus::Rejected);
                $other->setDecidedAt($clock->now());
                $other->touch();
            }
        }

        $listing->setStatus(ListingStatus::Reserved);
        $listing->touch();

        $adoptant = $application->getAdoptant();
        $cedant = $listing->getCedant();
        if (null !== $adoptant && null !== $cedant) {
            $conversation = $conversationRepository->findBetween($cedant, $adoptant, (int) $listing->getId())
                ?? (new Conversation())->setListing($listing);
            $conversation->addParticipant($cedant)->addParticipant($adoptant);

            $message = (new Message())
                ->setSender($cedant)
                ->setBody(sprintf('Bonne nouvelle : votre demande pour %s est acceptée. La remise est prévue le %s.', $listing->getName(), $application->getHandoverDate()?->format('d/m/Y') ?? '—'));
            $conversation->addMessage($message);
            $entityManager->persist($conversation);
            $entityManager->persist($message);
        }

        $entityManager->flush();
        $auditLogger->log('application.approved', $application, ['listingId' => $listing->getId()]);
        $this->addFlash('success', 'Demande acceptée. L\'annonce est désormais réservée.');

        return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
    }

    #[Route('/demandes/{id<\d+>}/refuser', name: 'app_application_reject', methods: ['POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function reject(
        AdoptionApplication $application,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ClockInterface $clock,
    ): Response {
        $listing = $this->denyUnlessListingOwner($application);

        if (!$this->isCsrfTokenValid('reject_application_'.$application->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$application->isActive()) {
            $this->addFlash('warning', 'Cette demande a déjà été traitée.');

            return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
        }

        $application->setStatus(ApplicationStatus::Rejected);
        $application->setDecidedAt($clock->now());
        $application->touch();

        if (ListingStatus::Reserved === $listing->getStatus()) {
            $listing->setStatus(ListingStatus::Published);
        }

        $entityManager->flush();
        $auditLogger->log('application.rejected', $application);
        $this->addFlash('success', 'Demande refusée.');

        return $this->redirectToRoute('app_application_received');
    }

    #[Route('/demandes/{id<\d+>}/remise', name: 'app_application_handover', methods: ['POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function handover(
        AdoptionApplication $application,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ClockInterface $clock,
    ): Response {
        $listing = $this->denyUnlessListingOwner($application);

        if (!$this->isCsrfTokenValid('handover_application_'.$application->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$application->isApproved()) {
            $this->addFlash('warning', 'Seule une demande acceptée peut être clôturée par une remise.');

            return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
        }

        $handoverDate = $application->getHandoverDate();
        if (null !== $handoverDate && $clock->now()->setTime(0, 0) < $handoverDate->setTime(0, 0)) {
            $this->addFlash('warning', 'La date de remise prévue n\'est pas encore arrivée.');

            return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
        }

        $application->setStatus(ApplicationStatus::HandedOver);
        $application->setHandedOverAt($clock->now());
        $application->touch();
        $listing->setStatus(ListingStatus::Closed);
        $listing->touch();

        $entityManager->flush();
        $auditLogger->log('application.handed_over', $application, ['listingId' => $listing->getId()]);
        $this->addFlash('success', 'Remise enregistrée. Merci de veiller au bien-être de l\'animal !');

        return $this->redirectToRoute('app_application_show', ['id' => $application->getId()]);
    }

    private function denyUnlessParticipant(AdoptionApplication $application): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $isAdoptant = $application->getAdoptant()?->getId() === $user->getId();
        $isCedant = $application->getListing()?->getCedant()?->getId() === $user->getId();

        if (!$isAdoptant && !$isCedant && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
    }

    private function denyUnlessListingOwner(AdoptionApplication $application): AnimalListing
    {
        $listing = $application->getListing();
        $user = $this->getUser();

        if (null === $listing || !$user instanceof User || ($listing->getCedant()?->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN'))) {
            throw $this->createAccessDeniedException();
        }

        return $listing;
    }
}

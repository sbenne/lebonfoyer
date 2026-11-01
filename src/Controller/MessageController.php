<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AnimalListing;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageFormType;
use App\Repository\ConversationRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MessageController extends AbstractController
{
    #[Route('/messages', name: 'app_message_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(ConversationRepository $conversationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('message/index.html.twig', [
            'conversations' => $conversationRepository->findByParticipant($user),
        ]);
    }

    #[Route('/messages/{id<\d+>}', name: 'app_message_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Conversation $conversation, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$conversation->hasParticipant($user) && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        foreach ($conversation->getMessages() as $message) {
            if ($message->getSender()?->getId() !== $user->getId()) {
                $message->markAsRead();
            }
        }
        $entityManager->flush();

        $form = $this->createForm(MessageFormType::class);

        return $this->render('message/show.html.twig', [
            'conversation' => $conversation,
            'form' => $form,
            'other' => $conversation->getOtherParticipant($user),
        ]);
    }

    #[Route('/annonces/{id<\d+>}/contacter', name: 'app_message_start', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function start(
        AnimalListing $listing,
        Request $request,
        ConversationRepository $conversationRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $cedant = $listing->getCedant();

        if (null === $cedant || $cedant->getId() === $user->getId()) {
            $this->addFlash('warning', 'Vous ne pouvez pas vous contacter vous-même.');

            return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
        }

        if (!$this->isCsrfTokenValid('contact_listing_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $conversation = $conversationRepository->findBetween($cedant, $user, (int) $listing->getId());
        if (null === $conversation) {
            $conversation = (new Conversation())
                ->setListing($listing)
                ->addParticipant($cedant)
                ->addParticipant($user);
            $entityManager->persist($conversation);
            $entityManager->flush();
            $auditLogger->log('conversation.started', $conversation, ['listingId' => $listing->getId()]);
        }

        return $this->redirectToRoute('app_message_show', ['id' => $conversation->getId()]);
    }

    #[Route('/messages/{id<\d+>}/envoyer', name: 'app_message_send', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function send(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ClockInterface $clock,
        #[Autowire(service: 'html_sanitizer.sanitizer.app.sanitizer.user_content')]
        HtmlSanitizerInterface $sanitizer,
        #[Autowire(service: 'limiter.message')]
        RateLimiterFactory $messageLimiter,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$conversation->hasParticipant($user)) {
            throw $this->createAccessDeniedException();
        }

        $limiter = $messageLimiter->create((string) $user->getId());
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Trop de messages envoyés. Patientez une minute.');

            return $this->redirectToRoute('app_message_show', ['id' => $conversation->getId()]);
        }

        $form = $this->createForm(MessageFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $body = trim($sanitizer->sanitize((string) $form->get('body')->getData()));
            if ('' === $body) {
                $this->addFlash('error', 'Le message est vide après nettoyage.');

                return $this->redirectToRoute('app_message_show', ['id' => $conversation->getId()]);
            }

            $message = (new Message())
                ->setSender($user)
                ->setBody($body);
            $conversation->addMessage($message);

            $entityManager->persist($message);
            $entityManager->flush();

            $auditLogger->log('message.sent', $conversation, ['messageId' => $message->getId()]);
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_message_show', ['id' => $conversation->getId()]);
    }
}

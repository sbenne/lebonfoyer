<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\ListingStatus;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
use App\Service\AuditLogger;
use App\Service\CityGeocoder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountController extends AbstractController
{
    #[Route('/compte', name: 'app_account_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        CityGeocoder $geocoder,
        AuditLogger $auditLogger,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $geocoder->geocode((string) $user->getCity(), $user->getPostalCode());
            if (null !== $result) {
                $user->setLatitude($result['latitude']);
                $user->setLongitude($result['longitude']);
                $user->setCity($result['name']);
                $user->setPostalCode($result['postalCode']);
            }

            $entityManager->flush();
            $auditLogger->log('account.updated', $user);
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('account/profile.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/compte/mot-de-passe', name: 'app_account_password', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function password(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$passwordHasher->isPasswordValid($user, (string) $form->get('currentPassword')->getData())) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
                $entityManager->flush();
                $auditLogger->log('account.password_changed', $user);
                $this->addFlash('success', 'Mot de passe modifié.');

                return $this->redirectToRoute('app_account_profile');
            }
        }

        return $this->render('account/password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/compte/export', name: 'app_account_export', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function export(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $listings = [];
        foreach ($user->getListings() as $listing) {
            $listings[] = [
                'id' => $listing->getId(),
                'name' => $listing->getName(),
                'species' => $listing->getSpecies()->value,
                'status' => $listing->getStatus()->value,
                'birthDate' => $listing->getBirthDate()?->format('Y-m-d'),
                'identificationNumber' => $listing->getIdentificationNumber(),
                'city' => $listing->getCity(),
                'createdAt' => $listing->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        $applications = [];
        foreach ($user->getApplications() as $application) {
            $applications[] = [
                'id' => $application->getId(),
                'listing' => $application->getListing()?->getName(),
                'status' => $application->getStatus()->value,
                'handoverDate' => $application->getHandoverDate()?->format('Y-m-d'),
                'createdAt' => $application->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        return new JsonResponse([
            'exportedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'profile' => [
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone(),
                'type' => $user->getType()->value,
                'cedantStatus' => $user->getCedantStatus()?->value,
                'city' => $user->getCity(),
                'postalCode' => $user->getPostalCode(),
                'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
            ],
            'listings' => $listings,
            'applications' => $applications,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="mes-donnees-lebonfoyer.json"',
        ]);
    }

    #[Route('/compte/supprimer', name: 'app_account_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        Security $security,
        ClockInterface $clock,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('delete_account_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        foreach ($user->getListings() as $listing) {
            if (ListingStatus::Closed !== $listing->getStatus()) {
                $listing->setStatus(ListingStatus::Suspended);
            }
        }

        $auditLogger->log('account.deleted', $user, ['email' => $user->getEmail()]);

        $user
            ->setEmail(sprintf('compte-supprime-%d@lebonfoyer.invalid', $user->getId()))
            ->setFirstName('Compte')
            ->setLastName('supprimé')
            ->setPhone(null)
            ->setIsActive(false)
            ->setDeletedAt($clock->now());

        $entityManager->flush();

        $security->logout(false);
        $this->addFlash('success', 'Votre compte a été supprimé et vos données personnelles effacées.');

        return $this->redirectToRoute('app_home');
    }
}

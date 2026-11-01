<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AnimalListing;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\ListingStatus;
use App\Repository\AdoptionApplicationRepository;
use App\Repository\AnimalListingRepository;
use App\Repository\AuditLogRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\PhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(
        AnimalListingRepository $listingRepository,
        UserRepository $userRepository,
        AdoptionApplicationRepository $applicationRepository,
        AuditLogRepository $auditLogRepository,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'listingCount' => (int) $listingRepository->createQueryBuilder('l')->select('COUNT(l.id)')->getQuery()->getSingleScalarResult(),
            'publishedCount' => $listingRepository->countVisible(),
            'userCount' => (int) $userRepository->createQueryBuilder('u')->select('COUNT(u.id)')->getQuery()->getSingleScalarResult(),
            'pendingCount' => $applicationRepository->countPending(),
            'logs' => $auditLogRepository->findLatest(15),
        ]);
    }

    #[Route('/annonces', name: 'app_admin_listings', methods: ['GET'])]
    public function listings(AnimalListingRepository $repository): Response
    {
        return $this->render('admin/listings.html.twig', [
            'listings' => $repository->createQueryBuilder('l')
                ->orderBy('l.createdAt', 'DESC')
                ->setMaxResults(200)
                ->getQuery()
                ->getResult(),
        ]);
    }

    #[Route('/annonces/{id<\d+>}/suspendre', name: 'app_admin_listing_suspend', methods: ['POST'])]
    public function suspendListing(
        AnimalListing $listing,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_suspend_listing_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $listing->setStatus(ListingStatus::Suspended)->touch();
        $entityManager->flush();
        $auditLogger->log('admin.listing_suspended', $listing);
        $this->addFlash('success', 'Annonce suspendue par la modération.');

        return $this->redirectToRoute('app_admin_listings');
    }

    #[Route('/annonces/{id<\d+>}/supprimer', name: 'app_admin_listing_delete', methods: ['POST'])]
    public function deleteListing(
        AnimalListing $listing,
        Request $request,
        EntityManagerInterface $entityManager,
        PhotoUploader $photoUploader,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_delete_listing_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        foreach ($listing->getPhotos() as $photo) {
            $photoUploader->delete($photo);
        }

        $data = ['listingId' => $listing->getId(), 'name' => $listing->getName()];
        $entityManager->remove($listing);
        $entityManager->flush();
        $auditLogger->log('admin.listing_deleted', null, $data);
        $this->addFlash('success', 'Annonce supprimée.');

        return $this->redirectToRoute('app_admin_listings');
    }

    #[Route('/utilisateurs', name: 'app_admin_users', methods: ['GET'])]
    public function users(UserRepository $repository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $repository->findRecent(200),
        ]);
    }

    #[Route('/utilisateurs/{id<\d+>}/basculer', name: 'app_admin_user_toggle', methods: ['POST'])]
    public function toggleUser(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_toggle_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
            $this->addFlash('warning', 'Vous ne pouvez pas désactiver votre propre compte.');

            return $this->redirectToRoute('app_admin_users');
        }

        $user->setIsActive(!$user->isActive());
        $entityManager->flush();
        $auditLogger->log($user->isActive() ? 'admin.user_enabled' : 'admin.user_disabled', $user);
        $this->addFlash('success', sprintf('Compte %s.', $user->isActive() ? 'réactivé' : 'désactivé'));

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/demandes', name: 'app_admin_applications', methods: ['GET'])]
    public function applications(AdoptionApplicationRepository $repository): Response
    {
        return $this->render('admin/applications.html.twig', [
            'applications' => $repository->createQueryBuilder('a')
                ->orderBy('a.createdAt', 'DESC')
                ->setMaxResults(200)
                ->getQuery()
                ->getResult(),
            'pendingStatus' => ApplicationStatus::Pending,
        ]);
    }

    #[Route('/journal', name: 'app_admin_audit', methods: ['GET'])]
    public function audit(AuditLogRepository $repository): Response
    {
        return $this->render('admin/audit.html.twig', [
            'logs' => $repository->findLatest(300),
        ]);
    }
}

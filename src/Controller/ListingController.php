<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AdoptionQuestion;
use App\Entity\AnimalListing;
use App\Entity\AnimalPhoto;
use App\Entity\User;
use App\Enum\ListingStatus;
use App\Form\ListingFormType;
use App\Form\ListingQuestionsFormType;
use App\Repository\AnimalListingRepository;
use App\Service\AuditLogger;
use App\Service\CityGeocoder;
use App\Service\PhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ListingController extends AbstractController
{
    private const PER_PAGE = 12;

    #[Route('/annonces', name: 'app_listing_index', methods: ['GET'])]
    public function index(Request $request, AnimalListingRepository $repository): Response
    {
        $species = (string) $request->query->get('species', '');
        $city = (string) $request->query->get('city', '');
        $query = (string) $request->query->get('q', '');
        $sort = (string) $request->query->get('sort', 'recent');
        $page = max(1, (int) $request->query->get('page', '1'));

        if (!\in_array($sort, ['recent', 'oldest', 'name'], true)) {
            $sort = 'recent';
        }

        $qb = $repository->search($species, $city, $query, $sort)
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb);
        $total = \count($paginator);

        return $this->render('listing/index.html.twig', [
            'listings' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / self::PER_PAGE),
            'species' => $species,
            'city' => $city,
            'query' => $query,
            'sort' => $sort,
        ]);
    }

    #[Route('/annonces/{id<\d+>}', name: 'app_listing_show', methods: ['GET'])]
    public function show(AnimalListing $listing): Response
    {
        $user = $this->getUser();
        if (!$listing->isVisible() && !$listing->isOwnedBy($user instanceof User ? $user : null) && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        return $this->render('listing/show.html.twig', [
            'listing' => $listing,
        ]);
    }

    #[Route('/mes-annonces', name: 'app_listing_mine', methods: ['GET'])]
    #[IsGranted('ROLE_CEDANT')]
    public function mine(AnimalListingRepository $repository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('listing/mine.html.twig', [
            'listings' => $repository->findByCedant((int) $user->getId()),
        ]);
    }

    #[Route('/mes-annonces/nouvelle', name: 'app_listing_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CityGeocoder $geocoder,
        AuditLogger $auditLogger,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (null === $user->getCedantStatus()) {
            $this->addFlash('warning', 'Complétez votre profil de cédant avant de publier une annonce.');

            return $this->redirectToRoute('app_account_profile');
        }

        $listing = (new AnimalListing())->setCedant($user);
        $form = $this->createForm(ListingFormType::class, $listing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyGeocoding($listing, $geocoder);
            $entityManager->persist($listing);
            $entityManager->flush();

            $auditLogger->log('listing.created', $listing);
            $this->addFlash('success', 'Annonce créée en brouillon. Ajoutez des photos puis publiez-la.');

            return $this->redirectToRoute('app_listing_edit', ['id' => $listing->getId()]);
        }

        return $this->render('listing/form.html.twig', [
            'form' => $form,
            'listing' => $listing,
            'questionsForm' => null,
        ]);
    }

    #[Route('/mes-annonces/{id<\d+>}/modifier', name: 'app_listing_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function edit(
        AnimalListing $listing,
        Request $request,
        EntityManagerInterface $entityManager,
        CityGeocoder $geocoder,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyUnlessOwnerOrAdmin($listing);

        $form = $this->createForm(ListingFormType::class, $listing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyGeocoding($listing, $geocoder);
            $listing->touch();
            $entityManager->flush();
            $auditLogger->log('listing.updated', $listing);
            $this->addFlash('success', 'Annonce mise à jour.');

            return $this->redirectToRoute('app_listing_edit', ['id' => $listing->getId()]);
        }

        $questionsForm = $this->createForm(ListingQuestionsFormType::class, null, ['listing' => $listing]);
        $questionsForm->handleRequest($request);

        if ($questionsForm->isSubmitted() && $questionsForm->isValid()) {
            foreach ($listing->getQuestions()->toArray() as $question) {
                $listing->removeQuestion($question);
            }

            for ($i = 0; $i < 10; ++$i) {
                $label = trim(strip_tags((string) $questionsForm->get('question_'.$i)->getData()));
                if ('' === $label) {
                    continue;
                }
                $question = (new AdoptionQuestion())
                    ->setLabel(mb_substr($label, 0, 300))
                    ->setPosition($i);
                $listing->addQuestion($question);
                $entityManager->persist($question);
            }

            $listing->touch();
            $entityManager->flush();
            $this->addFlash('success', 'Questionnaire mis à jour.');

            return $this->redirectToRoute('app_listing_edit', ['id' => $listing->getId()]);
        }

        return $this->render('listing/form.html.twig', [
            'form' => $form,
            'listing' => $listing,
            'questionsForm' => $questionsForm,
        ]);
    }

    #[Route('/mes-annonces/{id<\d+>}/publier', name: 'app_listing_publish', methods: ['POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function publish(
        AnimalListing $listing,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyUnlessOwnerOrAdmin($listing);

        if (!$this->isCsrfTokenValid('publish_listing_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $violations = $validator->validate($listing);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('error', $violation->getMessage());
            }

            return $this->redirectToRoute('app_listing_edit', ['id' => $listing->getId()]);
        }

        $listing->setStatus(ListingStatus::Published);
        if (null === $listing->getPublishedAt()) {
            $listing->setPublishedAt(new \DateTimeImmutable());
        }
        $listing->touch();
        $entityManager->flush();

        $auditLogger->log('listing.published', $listing);
        $this->addFlash('success', 'Votre annonce est publiée.');

        return $this->redirectToRoute('app_listing_show', ['id' => $listing->getId()]);
    }

    #[Route('/mes-annonces/{id<\d+>}/suspendre', name: 'app_listing_suspend', methods: ['POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function suspend(AnimalListing $listing, Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyUnlessOwnerOrAdmin($listing);

        if (!$this->isCsrfTokenValid('suspend_listing_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $listing->setStatus(ListingStatus::Suspended);
        $listing->touch();
        $entityManager->flush();
        $auditLogger->log('listing.suspended', $listing);
        $this->addFlash('success', 'Annonce suspendue.');

        return $this->redirectToRoute('app_listing_mine');
    }

    #[Route('/mes-annonces/{id<\d+>}/supprimer', name: 'app_listing_delete', methods: ['POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function delete(
        AnimalListing $listing,
        Request $request,
        EntityManagerInterface $entityManager,
        PhotoUploader $photoUploader,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyUnlessOwnerOrAdmin($listing);

        if (!$this->isCsrfTokenValid('delete_listing_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        foreach ($listing->getPhotos() as $photo) {
            $photoUploader->delete($photo);
        }

        $entityManager->remove($listing);
        $entityManager->flush();
        $auditLogger->log('listing.deleted', null, ['listingId' => $listing->getId(), 'name' => $listing->getName()]);
        $this->addFlash('success', 'Annonce supprimée.');

        return $this->redirectToRoute('app_listing_mine');
    }

    #[Route('/mes-annonces/{id<\d+>}/photos', name: 'app_listing_photo_upload', methods: ['POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function uploadPhotos(
        AnimalListing $listing,
        Request $request,
        EntityManagerInterface $entityManager,
        PhotoUploader $photoUploader,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyUnlessOwnerOrAdmin($listing);

        if (!$this->isCsrfTokenValid('photos_listing_'.$listing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $files = $request->files->all('photos');

        $added = 0;
        foreach ($files as $file) {
            if (!\in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                $this->addFlash('error', 'Format non supporté : utilisez JPEG, PNG, GIF ou WebP.');
                continue;
            }
            if ($file->getSize() > 8 * 1024 * 1024) {
                $this->addFlash('error', 'Chaque photo doit faire moins de 8 Mo.');
                continue;
            }

            try {
                $photo = $photoUploader->upload($file);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Photo non importée : '.$e->getMessage());
                continue;
            }

            $photo->setIsCover(0 === $listing->getPhotos()->count() && 0 === $added);
            $listing->addPhoto($photo);
            $entityManager->persist($photo);
            ++$added;
        }

        $listing->touch();
        $entityManager->flush();
        if ($added > 0) {
            $auditLogger->log('listing.photos_added', $listing, ['count' => $added]);
            $this->addFlash('success', sprintf('%d photo(s) ajoutée(s).', $added));
        }

        return $this->redirectToRoute('app_listing_edit', ['id' => $listing->getId()]);
    }

    #[Route('/mes-annonces/{id<\d+>}/photos/{photoId<\d+>}/supprimer', name: 'app_listing_photo_delete', methods: ['POST'])]
    #[IsGranted('ROLE_CEDANT')]
    public function deletePhoto(
        AnimalListing $listing,
        int $photoId,
        Request $request,
        EntityManagerInterface $entityManager,
        PhotoUploader $photoUploader,
    ): Response {
        $this->denyUnlessOwnerOrAdmin($listing);

        if (!$this->isCsrfTokenValid('delete_photo_'.$photoId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $photo = $entityManager->getRepository(AnimalPhoto::class)->find($photoId);
        if (!$photo instanceof AnimalPhoto || $photo->getListing()?->getId() !== $listing->getId()) {
            throw $this->createNotFoundException('Photo introuvable.');
        }

        $wasCover = $photo->isCover();
        $photoUploader->delete($photo);
        $listing->removePhoto($photo);
        $entityManager->remove($photo);
        $entityManager->flush();

        if ($wasCover && null !== ($next = $listing->getCoverPhoto())) {
            $next->setIsCover(true);
            $entityManager->flush();
        }

        $this->addFlash('success', 'Photo supprimée.');

        return $this->redirectToRoute('app_listing_edit', ['id' => $listing->getId()]);
    }

    private function denyUnlessOwnerOrAdmin(AnimalListing $listing): void
    {
        $user = $this->getUser();
        if (!$listing->isOwnedBy($user instanceof User ? $user : null) && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas propriétaire de cette annonce.');
        }
    }

    private function applyGeocoding(AnimalListing $listing, CityGeocoder $geocoder): void
    {
        $result = $geocoder->geocode($listing->getCity(), $listing->getPostalCode());
        if (null !== $result) {
            $listing->setLatitude($result['latitude']);
            $listing->setLongitude($result['longitude']);
            $listing->setCity($result['name']);
            $listing->setPostalCode($result['postalCode']);
        }
    }
}

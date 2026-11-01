<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AnimalListingRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(AnimalListingRepository $listingRepository, UserRepository $userRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'listings' => array_slice($listingRepository->findVisible(), 0, 6),
            'listingCount' => $listingRepository->countVisible(),
            'userCount' => (int) $userRepository->createQueryBuilder('u')->select('COUNT(u.id)')->getQuery()->getSingleScalarResult(),
        ]);
    }

    #[Route('/regles-de-bien-etre', name: 'app_welfare_rules', methods: ['GET'])]
    public function welfareRules(): Response
    {
        return $this->render('home/welfare_rules.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_legal_mentions', methods: ['GET'])]
    public function legalMentions(): Response
    {
        return $this->render('legal/mentions.html.twig');
    }

    #[Route('/confidentialite', name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }
}

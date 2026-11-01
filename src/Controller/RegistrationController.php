<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        Security $security,
        AuditLogger $auditLogger,
        LoggerInterface $logger,
        VerifyEmailHelperInterface $verifyEmailHelper,
        #[Autowire(service: 'limiter.registration')]
        RateLimiterFactory $registrationLimiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $limiter = $registrationLimiter->create($request->getClientIp() ?? 'anonymous');
        if ($request->isMethod('POST') && !$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives d\'inscription. Réessayez dans une heure.');

            return $this->redirectToRoute('app_register');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($user->isCedant() && null === $user->getCedantStatus()) {
                $this->addFlash('error', 'Précisez votre statut de cédant (particulier, association ou professionnel).');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));

                // En développement, le mailer est désactivé : on vérifie directement le compte.
                if ('prod' !== $this->getParameter('kernel.environment')) {
                    $user->setIsVerified(true);
                }

                $entityManager->persist($user);
                $entityManager->flush();

                $auditLogger->log('user.registered', $user, ['type' => $user->getType()->value]);

                if (!$user->isVerified()) {
                    $signature = $verifyEmailHelper->generateSignature(
                        'app_verify_email',
                        (string) $user->getId(),
                        $user->getEmail(),
                        ['id' => $user->getId()],
                    );
                    $logger->info('Lien de vérification email : {url}', ['url' => $signature->getSignedUrl()]);
                }

                $security->login($user);
                $this->addFlash('success', 'Bienvenue sur LeBonFoyer ! Votre compte est créé.');

                return $this->redirectToRoute($user->isCedant() ? 'app_listing_mine' : 'app_listing_index');
            }
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verifier-email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(
        Request $request,
        UserRepository $userRepository,
        VerifyEmailHelperInterface $verifyEmailHelper,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $id = $request->query->get('id');
        if (null === $id) {
            return $this->redirectToRoute('app_home');
        }

        $user = $userRepository->find((int) $id);
        if (null === $user) {
            $this->addFlash('error', 'Lien de vérification invalide.');

            return $this->redirectToRoute('app_home');
        }

        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', 'Le lien de vérification est invalide ou expiré : '.$e->getReason());

            return $this->redirectToRoute('app_home');
        }

        if (!$user->isVerified()) {
            $user->setIsVerified(true);
            $entityManager->flush();
        }

        $this->addFlash('success', 'Votre adresse email est confirmée. Vous pouvez maintenant réserver.');

        return $this->redirectToRoute('app_home');
    }
}

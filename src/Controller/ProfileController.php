<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profile', name: 'profile_')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityLogger $activityLogger,
    ) {}

    #[Route('', name: 'show')]
    public function show(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $logs = $this->em->getRepository(\App\Entity\ActivityLog::class)
            ->findBy(['user' => $user], ['createdAt' => 'DESC'], 20);

        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'logs' => $logs,
        ]);
    }

    #[Route('/settings', name: 'settings')]
    public function settings(
        Request $request,
        UserPasswordHasherInterface $hasher,
    ): Response {
        /** @var User $user */
        $user  = $this->getUser();
        $error = null;
        $tab   = $request->query->get('tab', 'password');

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            if ($action === 'change_password') {
                $current  = $request->request->get('current_password');
                $new      = $request->request->get('new_password');
                $confirm  = $request->request->get('confirm_password');

                if (!$hasher->isPasswordValid($user, $current)) {
                    $error = 'Current password is incorrect.';
                } elseif (strlen($new) < 8) {
                    $error = 'New password must be at least 8 characters.';
                } elseif ($new !== $confirm) {
                    $error = 'Passwords do not match.';
                } else {
                    $user->setPassword($hasher->hashPassword($user, $new));
                    $user->setMustChangePassword(false);
                    $this->em->flush();
                    $this->activityLogger->log($user, 'password_changed');
                    $this->addFlash('success', 'Password updated successfully.');
                    return $this->redirectToRoute('profile_settings', ['tab' => 'password']);
                }

            } elseif ($action === 'update_profile') {
                $user->setFirstName($request->request->get('first_name'));
                $user->setLastName($request->request->get('last_name'));
                $this->em->flush();
                $this->activityLogger->log($user, 'profile_updated');
                $this->addFlash('success', 'Profile updated.');
                return $this->redirectToRoute('profile_settings', ['tab' => 'profile']);

            } elseif ($action === 'update_theme') {
                $theme = $request->request->get('theme', 'corporate');
                $user->setTheme($theme);
                $this->em->flush();
                return $this->redirectToRoute('profile_settings', ['tab' => 'appearance']);
            }
        }

        return $this->render('profile/settings.html.twig', [
            'user'  => $user,
            'error' => $error,
            'tab'   => $tab,
        ]);
    }
}

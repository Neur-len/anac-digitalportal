<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/auth/login', name: 'auth_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        UserPasswordHasherInterface $hasher,
        AuthenticationUtils $authUtils,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error    = $authUtils->getLastAuthenticationError();
        $step     = $request->getSession()->get('auth_step', 'credentials');
        $email    = $request->getSession()->get('auth_email', '');

        if ($request->isMethod('POST')) {
            $step = $request->request->get('step', 'credentials');

            if ($step === 'credentials') {
                $email    = $request->request->get('email');
                $password = $request->request->get('password');
                $user     = $em->getRepository(User::class)->findOneBy(['email' => $email]);

                if (!$user || !$hasher->isPasswordValid($user, $password) || !$user->isActive()) {
                    $error = 'Invalid email or password.';
                } else {
                    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $user->setOtpCode($otp);
                    $user->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));
                    $em->flush();
                    file_put_contents(dirname(__DIR__, 2) . '/var/log/otp.log', date('H:i:s') . " OTP for $email: $otp\n", FILE_APPEND);

                    $mailer->send(
                        (new Email())
                            ->from('noreply@portal.local')
                            ->to($email)
                            ->subject('Your login code')
                            ->text("Your Digital Portal login code is: $otp\n\nValid for 10 minutes.")
                    );

                    $request->getSession()->set('auth_step', 'otp');
                    $request->getSession()->set('auth_email', $email);
                    return $this->redirectToRoute('auth_login');
                }

            } elseif ($step === 'otp') {
                // Handled by LoginAuthenticator
            }
        }

        return $this->render('auth/login.html.twig', [
            'step'  => $step,
            'email' => $email,
            'error' => $error ? (is_string($error) ? $error : $error->getMessageKey()) : null,
        ]);
    }

    #[Route('/auth/logout', name: 'auth_logout')]
    public function logout(): void {}

    #[Route('/auth/invite/{token}', name: 'auth_invite', methods: ['GET', 'POST'])]
    public function invite(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $em->getRepository(User::class)->findOneBy(['inviteToken' => $token]);

        if (!$user || $user->getInviteTokenExpiresAt() < new \DateTimeImmutable()) {
            return $this->render('auth/invite_invalid.html.twig');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirm  = $request->request->get('confirm');

            if (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setInviteToken(null);
                $user->setInviteTokenExpiresAt(null);
                $user->setIsActive(true);
                $em->flush();
                return $this->redirectToRoute('auth_invite_done');
            }
        }

        return $this->render('auth/invite.html.twig', [
            'email' => $user->getEmail(),
            'error' => $error,
            'token' => $token,
        ]);
    }

    #[Route('/auth/invite/done', name: 'auth_invite_done')]
    public function inviteDone(): Response
    {
        return $this->render('auth/invite_done.html.twig');
    }

    #[Route('/auth/change-password', name: 'auth_change_password', methods: ['GET', 'POST'])]
public function changePassword(
    Request $request,
    EntityManagerInterface $em,
    UserPasswordHasherInterface $hasher,
): Response {
    $user  = $this->getUser();
    $error = null;

    if ($request->isMethod('POST')) {
        $password = $request->request->get('password');
        $confirm  = $request->request->get('confirm');

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $user->setPassword($hasher->hashPassword($user, $password));
            $user->setMustChangePassword(false);
            $em->flush();
            return $this->redirectToRoute('app_home');
        }
    }

    return $this->render('auth/change_password.html.twig', ['error' => $error]);
}
}

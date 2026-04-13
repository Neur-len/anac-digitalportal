<?php

namespace App\Security;

use App\Service\ActivityLogger;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class LoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private ActivityLogger $activityLogger,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'auth_login'
            && $request->isMethod('POST')
            && $request->request->get('step') === 'otp';  // ← add this
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $otp   = $request->request->get('otp', '');

        return new Passport(
            new UserBadge($email, function (string $email) use ($otp) {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

                if (!$user || !$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                if (!$otp) {
                    // Stage 1 — OTP not yet submitted, handled by controller
                    throw new CustomUserMessageAuthenticationException('otp_required');
                }

                // Stage 2 — Validate OTP
                if ($user->getOtpCode() !== $otp) {
                    throw new CustomUserMessageAuthenticationException('Invalid OTP code.');
                }

                if ($user->getOtpExpiresAt() < new \DateTimeImmutable()) {
                    throw new CustomUserMessageAuthenticationException('OTP expired. Please try again.');
                }

                $user->setOtpCode(null);
                $user->setOtpExpiresAt(null);
                $this->em->flush();

                return $user;
            }),
            new CustomCredentials(fn() => true, $otp),
            [new CsrfTokenBadge('auth', $request->request->get('_csrf_token'))]
        );
    }

    // public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    // {
    //     return new RedirectResponse($this->router->generate('app_home'));
    // }


    // Inject in constructor

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof \App\Entity\User) {
            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->activityLogger->log($user, 'login', [
                'ip' => $request->getClientIp(),
            ]);
        }
        return new RedirectResponse($this->router->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set('auth_error', $exception->getMessage());
        return new RedirectResponse($this->router->generate('auth_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('auth_login'));
    }
}
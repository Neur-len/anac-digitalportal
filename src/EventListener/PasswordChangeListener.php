<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 8)]
class PasswordChangeListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        // Skip public routes
        if (in_array($route, ['auth_login', 'auth_logout', 'auth_change_password', 'auth_invite', 'auth_invite_done', '_wdt', '_profiler'])) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user  = $token?->getUser();

        if ($user instanceof User && $user->isMustChangePassword()) {
            $event->setResponse(
                new RedirectResponse($this->router->generate('auth_change_password'))
            );
        }
    }
}
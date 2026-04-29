<?php

namespace App\Controller;

use App\Entity\Applet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\DependencyInjection\Attribute\Target;



#[Route('/proxy', name: 'proxy_')]
class ProxyController extends AbstractController
{
    public function __construct(
            private HttpClientInterface $httpClient,
            private EntityManagerInterface $em,
            #[Target('proxy_limiter.limiter')] // <-- Add .limiter here
            private RateLimiterFactory $proxyLimiter, 
        ) {}

    #[Route('/{slug}/{path}', name: 'applet', requirements: ['slug' => '[a-z0-9_-]+', 'path' => '.*'], defaults: ['path' => ''])]
    public function proxy(string $slug, string $path, Request $request): Response
    {
        // --- ADD THIS BLOCK AT THE START OF THE METHOD ---
        // Create a limit based on the User ID (or IP if they aren't logged in)
        $limitKey = $this->getUser() ? $this->getUser()->getUserIdentifier() : $request->getClientIp();
        $limiter = $this->proxyLimiter->create($limitKey);

        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException('Rate limit exceeded. Please wait a few minutes.');
        }
        $applet = $this->em->getRepository(Applet::class)->findOneBy(['slug' => $slug]);

        if (!$applet) {
            throw $this->createNotFoundException("Applet '$slug' not found.");
        }

        $targetUrl = rtrim($applet->getUrl(), '/') . '/' . ltrim($path, '/');

        try {
            $response = $this->httpClient->request(
                $request->getMethod(),
                $targetUrl,
                [
                    'headers' => [
                        'X-Forwarded-For'  => $request->getClientIp(),
                        'X-Portal-User'    => $this->getUser()?->getUserIdentifier() ?? 'guest',
                        'X-Forwarded-Host' => $request->getHost(),
                    ],
                    'query' => $request->query->all(),
                    'body'  => $request->getContent(),
                ]
            );

            $contentType = $response->getHeaders(false)['content-type'][0] ?? 'text/html';

            return new Response(
                $response->getContent(false),
                $response->getStatusCode(),
                ['Content-Type' => $contentType]
            );
        } catch (\Exception $e) {
            return $this->render('applet/unavailable.html.twig', [
                'applet' => $applet,
                'slug'   => $slug,
            ]);
        }
    }
}
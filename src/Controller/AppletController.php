<?php

namespace App\Controller;

use App\Entity\Applet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/applet', name: 'applet_')]
class AppletController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/{slug}', name: 'show', requirements: ['slug' => '[a-z0-9_-]+'])]
    public function show(string $slug): Response
    {
        $applet = $this->em->getRepository(Applet::class)->findOneBy(['slug' => $slug]);

        if (!$applet) {
            throw $this->createNotFoundException("Applet '$slug' not found.");
        }

        return $this->render('applet/show.html.twig', ['applet' => $applet]);
    }
}
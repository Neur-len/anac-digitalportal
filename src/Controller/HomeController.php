<?php

namespace App\Controller;

use App\Entity\Applet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/*************  ✨ Windsurf Command ⭐  *************/
/**
 * Displays the homepage.
 *
 * @return Response
 */
/*******  6f61a4d4-c7be-4511-ae9e-7ea4a69297ab  *******/class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        $allApplets = $em->getRepository(Applet::class)->findBy(['status' => 'online']);
        $userRoles  = $this->getUser()?->getRoles() ?? [];

        $applets = array_filter(
            $allApplets,
            fn(Applet $a) => empty($a->getAllowedRoles())
                || array_intersect($a->getAllowedRoles(), $userRoles)
        );

        return $this->render('home/index.html.twig', [
            'applets' => $applets,
        ]);
    }
}
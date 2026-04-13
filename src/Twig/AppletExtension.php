<?php

  namespace App\Twig;
  
  use App\Entity\Applet;
  use Doctrine\ORM\EntityManagerInterface;
  use Twig\Extension\AbstractExtension;
  use Twig\TwigFunction;
  
  /**
   * Return an array of twig functions.
   * @return array
   */
  class AppletExtension extends AbstractExtension
  {
      public function __construct(private EntityManagerInterface $em) {}
  
      public function getFunctions(): array
      {
          return [
            new TwigFunction('get_nav_applets', $this->getNavApplets(...)),
          ];
      }
  
      public function getNavApplets(): array
      {
        return $this->em->getRepository(Applet::class)->findBy(['status' => 'online']);
      }
  }
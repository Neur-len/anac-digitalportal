<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ActivityLogger
{
    public function __construct(private EntityManagerInterface $em) {}

    public function log(User $user, string $action, array $context = []): void
    {
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction($action);
        $log->setContext($context);
        $log->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($log);
        $this->em->flush();
    }
}

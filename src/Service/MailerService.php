<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
    ) {}

    public function sendInvite(string $to, string $inviteUrl): void
    {
        $this->mailer->send(
            (new Email())
                ->from('noreply@portal.local')
                ->to($to)
                ->subject('You are invited to Digital Portal')
                ->html($this->twig->render('emails/invite.html.twig', ['invite_url' => $inviteUrl]))
                ->text($this->twig->render('emails/invite.txt.twig', ['invite_url' => $inviteUrl]))
        );
    }

    public function sendCredentials(string $to, string $password, string $loginUrl): void
    {
        $this->mailer->send(
            (new Email())
                ->from('noreply@portal.local')
                ->to($to)
                ->subject('Your Digital Portal credentials')
                ->html($this->twig->render('emails/credentials.html.twig', [
                    'email'     => $to,
                    'password'  => $password,
                    'login_url' => $loginUrl,
                ]))
                ->text($this->twig->render('emails/credentials.txt.twig', [
                    'email'     => $to,
                    'password'  => $password,
                    'login_url' => $loginUrl,
                ]))
        );
    }
}
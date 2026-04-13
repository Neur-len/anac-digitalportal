<?php

namespace App\Controller;

use App\Entity\Applet;
use App\Entity\User;
use App\Form\AppletType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\UserType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Form\UserCreateType;
use App\Service\MailerService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'applet_count' => $this->em->getRepository(Applet::class)->count([]),
            'user_count'   => $this->em->getRepository(User::class)->count([]),
        ]);
    }

    #[Route('/applets', name: 'applets')]
    public function applets(): Response
    {
        return $this->render('admin/applets/index.html.twig', [
            'applets' => $this->em->getRepository(Applet::class)->findAll(),
        ]);
    }

    #[Route('/applets/new', name: 'applet_new')]
    public function appletNew(Request $request): Response
    {
        $applet = new Applet();
        $form   = $this->createForm(AppletType::class, $applet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $applet->setCreatedAt(new \DateTimeImmutable());
            $this->em->persist($applet);
            $this->em->flush();
            $this->addFlash('success', 'Applet registered successfully.');
            return $this->redirectToRoute('admin_applets');
        }

        return $this->render('admin/applets/form.html.twig', [
            'form'  => $form,
            'title' => 'Register New Applet',
        ]);
    }

    #[Route('/applets/{id}/edit', name: 'applet_edit')]
    public function appletEdit(Applet $applet, Request $request): Response
    {
        $form = $this->createForm(AppletType::class, $applet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Applet updated.');
            return $this->redirectToRoute('admin_applets');
        }

        return $this->render('admin/applets/form.html.twig', [
            'form'  => $form,
            'title' => 'Edit Applet',
        ]);
    }

    #[Route('/applets/{id}/delete', name: 'applet_delete', methods: ['POST'])]
    public function appletDelete(Applet $applet, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete-applet-'.$applet->getId(), $request->request->get('_token'))) {
            $this->em->remove($applet);
            $this->em->flush();
            $this->addFlash('success', 'Applet deleted.');
        }

        return $this->redirectToRoute('admin_applets');
    }

    #[Route('/users', name: 'users')]
    public function users(): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $this->em->getRepository(User::class)->findAll(),
        ]);
    }


#[Route('/users/new', name: 'user_new')]
public function userNew(Request $request, MailerInterface $mailer): Response
{
    $user = new User();
    $form = $this->createForm(UserType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Generate invite token
        $token = bin2hex(random_bytes(32));
        $user->setInviteToken($token);
        $user->setInviteTokenExpiresAt(new \DateTimeImmutable('+48 hours'));
        $user->setPassword('');       // empty until user sets it
        $user->setIsActive(false);    // inactive until invite accepted
        $this->em->persist($user);
        $this->em->flush();

        // Send invite email
        $inviteUrl = $this->generateUrl(
            'auth_invite',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $mailer->send(
            (new Email())
                ->from('noreply@portal.local')
                ->to($user->getEmail())
                ->subject('You are invited to Digital Portal')
                ->text("You have been invited to Digital Portal.\n\nClick the link to set your password:\n$inviteUrl\n\nThis link expires in 48 hours.")
        );

        $this->addFlash('success', "Invite sent to {$user->getEmail()}.");
        return $this->redirectToRoute('admin_users');
    }

    return $this->render('admin/users/form.html.twig', [
        'form'  => $form,
        'title' => 'Invite User',
    ]);
}

#[Route('/users/{id}/edit', name: 'user_edit')]
public function userEdit(User $user, Request $request): Response
{
    $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $this->em->flush();
        $this->addFlash('success', 'User updated.');
        return $this->redirectToRoute('admin_users');
    }

    return $this->render('admin/users/form.html.twig', [
        'form'  => $form,
        'title' => 'Edit User',
    ]);
}

#[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
public function userDelete(User $user, Request $request): Response
{
    if ($this->isCsrfTokenValid('delete-user-'.$user->getId(), $request->request->get('_token'))) {
        $this->em->remove($user);
        $this->em->flush();
        $this->addFlash('success', 'User deleted.');
    }
    return $this->redirectToRoute('admin_users');
}

#[Route('/users/{id}/resend-invite', name: 'user_resend_invite', methods: ['POST'])]
public function resendInvite(User $user, MailerInterface $mailer): Response
{
    $token = bin2hex(random_bytes(32));
    $user->setInviteToken($token);
    $user->setInviteTokenExpiresAt(new \DateTimeImmutable('+48 hours'));
    $this->em->flush();

    $inviteUrl = $this->generateUrl(
        'auth_invite',
        ['token' => $token],
        UrlGeneratorInterface::ABSOLUTE_URL
    );

    $mailer->send(
        (new Email())
            ->from('noreply@portal.local')
            ->to($user->getEmail())
            ->subject('Your Digital Portal invitation')
            ->text("Click to set your password:\n$inviteUrl\n\nExpires in 48 hours.")
    );

    $this->addFlash('success', 'Invite resent.');
    return $this->redirectToRoute('admin_users');
}


#[Route('/users/create', name: 'user_create')]
public function userCreate(
    Request $request,
    UserPasswordHasherInterface $hasher,
    MailerService $mailerService,
): Response {
    $user = new User();
    $form = $this->createForm(UserCreateType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $plainPassword = $form->get('plainPassword')->getData();

        $user->setPassword($hasher->hashPassword($user, $plainPassword));
        $user->setIsActive(true);
        $user->setMustChangePassword(true);
        $this->em->persist($user);
        $this->em->flush();

        $loginUrl = $this->generateUrl('auth_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $mailerService->sendCredentials($user->getEmail(), $plainPassword, $loginUrl);

        $this->addFlash('success', "User {$user->getEmail()} created. Credentials sent.");
        return $this->redirectToRoute('admin_users');
    }

    return $this->render('admin/users/create.html.twig', [
        'form'  => $form,
        'title' => 'Create User',
    ]);
}
}
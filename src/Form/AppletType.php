<?php

namespace App\Form;

use App\Entity\Applet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppletType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'input input-bordered w-full'],
            ])
            ->add('slug', TextType::class, [
            'attr' => ['class' => 'input input-bordered w-full'],
            'help' => 'URL key e.g. crm, hr, finance (lowercase, no spaces)',
            ])
            ->add('url', TextType::class, [
                'attr' => ['class' => 'input input-bordered w-full'],
                'help' => 'Internal container URL e.g. http://applet-crm:8000',
            ])
            ->add('icon', TextType::class, [
                'attr'     => ['class' => 'input input-bordered w-full'],
                'help'     => 'Emoji or icon string e.g. 📦',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Online'      => 'online',
                    'Offline'     => 'offline',
                    'Maintenance' => 'maintenance',
                ],
                'attr' => ['class' => 'select select-bordered w-full'],
            ])
            ->add('category', TextType::class, [
                'attr'     => ['class' => 'input input-bordered w-full'],
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'attr'     => ['class' => 'textarea textarea-bordered w-full', 'rows' => 3],
                'required' => false,
            ])
            ->add('allowedRoles', ChoiceType::class, [
                'choices'  => [
                    'Admin'  => 'ROLE_ADMIN',
                    'User'   => 'ROLE_USER',
                    'Editor' => 'ROLE_EDITOR',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr'     => ['class' => 'flex gap-4'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Applet::class]);
    }
}
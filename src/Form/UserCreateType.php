<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => ['class' => 'input input-bordered w-full'],
            ])
            ->add('roles', ChoiceType::class, [
                'choices'  => [
                    'User'   => 'ROLE_USER',
                    'Admin'  => 'ROLE_ADMIN',
                    'Editor' => 'ROLE_EDITOR',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('isAdmin', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'mapped'   => false,
                'required' => false,
                'label'    => 'Grant admin role (ROLE_ADMIN)',
                'attr'     => ['class' => 'checkbox checkbox-primary'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'           => PasswordType::class,
                'mapped'         => false,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => [
                        'placeholder' => 'Enter a strong password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                    'attr' => [
                        'placeholder' => 'Repeat password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'constraints' => [
        new NotBlank(),
        new Length(['min' => 8]),
        new Regex([
            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
            'message' => 'Password must contain at least:
            - one uppercase letter
            - one lowercase letter
            - one number
            - one special character'
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
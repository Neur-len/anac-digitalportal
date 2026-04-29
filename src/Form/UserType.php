<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
// Ajout des contraintes
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('firstName', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'First name',
                    'autocomplete' => 'given-name',
                ],
            ])
            ->add('lastName', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'Last name',
                    'autocomplete' => 'family-name',
                ],
            ])
            ->add('email', EmailType::class, [
                'attr'     => ['class' => 'input input-bordered w-full'],
                'disabled' => $isEdit,
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
            ->add('isActive', CheckboxType::class, [
                'required' => false,
                'label'    => 'Active account',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => !$isEdit,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => [
                        'placeholder' => $isEdit ? 'Leave blank to keep current' : 'Enter password',
                        'autocomplete' => 'new-password',
                        'class' => 'input input-bordered w-full',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                    'attr' => [
                        'placeholder' => 'Repeat password',
                        'autocomplete' => 'new-password',
                        'class' => 'input input-bordered w-full',
                    ],
                ],
                // AJOUT DES CONTRAINTES ICI
                'constraints' => array_merge(
                    $isEdit ? [] : [new NotBlank(['message' => 'Please enter a password'])],
                    [
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Your password should be at least {{ limit }} characters',
                        ]),
                        new Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
                            'message' => 'Password must contain at least one uppercase, one lowercase, one number and one special character.'
                        ]),
                    ]
                ),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit'    => false,
        ]);
    }
}
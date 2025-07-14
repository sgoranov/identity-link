<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user_id', TextType::class, [
                'label' => 'form_login.user_id',
            ])
            ->add('password', PasswordType::class, [
                'label' => 'form_login.password',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'form_login.submit',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $constraints = [
            'user_id' => new NotBlank(),
            'password' => new NotBlank(),
        ];

        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => $constraints,
            'csrf_protection' => true,
            'csrf_field_name' => 'csrf_token',
            'csrf_token_id'   => 'login',
        ]);
    }
}
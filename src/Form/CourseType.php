<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, введите код курса',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Код курса не может быть длиннее {{ limit }} символов',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_\-]+$/',
                        'message' => 'Код курса может содержать только буквы, цифры, дефисы и подчеркивания',
                    ]),
                ],
            ])
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, введите название курса',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Название курса не может быть длиннее {{ limit }} символов',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Описание не может быть длиннее {{ limit }} символов',
                    ]),
                ],
                'attr' => [
                    'rows' => 5,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            'attr' => [
                'novalidate' => 'novalidate', // отключаем HTML5 валидацию
            ],
        ]);
    }
}

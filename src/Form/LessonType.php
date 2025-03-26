<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\DataTransformer\CourseToIdTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;

class LessonType extends AbstractType
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, введите название урока',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Название урока не может быть длиннее {{ limit }} символов',
                    ]),
                ],
            ])
            ->add('content', TextareaType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, введите содержание урока',
                    ]),
                ],
                'attr' => [
                    'rows' => 10,
                ],
            ])
            ->add('orderNumber', IntegerType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, укажите порядковый номер',
                    ]),
                    new Positive([
                        'message' => 'Порядковый номер должен быть положительным числом',
                    ]),
                ],
                'attr' => [
                    'min' => 1,
                    'max' => 10000,
                ],
            ])
            ->add('course', HiddenType::class, [
                'data' => $options['course_id']
            ]);

        $transformer = new CourseToIdTransformer($this->em);
        $builder->get('course')
            ->addModelTransformer($transformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'course_id' => null,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);
    }
}

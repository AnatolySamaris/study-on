<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\DataTransformer\CourseToIdTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LessonType extends AbstractType
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('content')
            ->add('orderNumber')
            ->add('course', HiddenType::class, [
                'data' => $options['course_id']
            ])
        ;

        $transformer = new CourseToIdTransformer($this->em);
        $builder->get('course')
            ->addModelTransformer($transformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'course_id' => null,
        ]);
    }
}

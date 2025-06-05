<?php

namespace App\Form;

use App\Enum\CourseType as EnumCourseType;
use App\Entity\Course;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class)
            ->add('title', TextType::class)
            ->add('description', TextareaType::class)
            ->add('type', ChoiceType::class, [
                'required' => true,
                'choices'  => [
                    'Free' => EnumCourseType::FREE->value,
                    'Rent' => EnumCourseType::RENT->value,
                    'Pay' => EnumCourseType::PAY->value,
                ]
            ])
            ->add("price", MoneyType::class, [
                'required' => false,
                'currency' => 'RUB',
            ]);
        ;

        $builder->get('price')
            ->addModelTransformer(new CallbackTransformer(
                function ($priceAsString): float {
                    return (float)$priceAsString;
                },
                function ($price): string {
                    return (string)$price;
                }
            ))
        ;

        $builder->get('type')
            ->addModelTransformer(new CallbackTransformer(
                function (?EnumCourseType $type): ?int {
                    return $type?->value;
                },
                function (?int $type): ?EnumCourseType {
                    return $type !== null ? EnumCourseType::from($type) : null;
                }
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => Course::class,
            "constraints" => [
                new UniqueEntity([
                    "entityClass" => Course::class,
                    "fields" => ["code"],
                ]),
            ],
            'price' => 0.0,
            'type' => EnumCourseType::FREE->value
        ]);

        $resolver->addAllowedTypes('price', ['int', 'float']);
        $resolver->addAllowedTypes('type', 'int');
    }
}

<?php

namespace App\Form\DataTransformer;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class CourseToIdTransformer implements DataTransformerInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function transform($course): mixed
    {
        if (null === $course) {
            return null;
        }

        if ($course instanceof Course) {
            return $course->getId();
        } else {
            return $course;
        }
    }

    public function reverseTransform($courseId): mixed
    {
        if (!$courseId) {
            return null;
        }

        $course = $this->em->getRepository(Course::class)->find($courseId);

        return $course;
    }
}

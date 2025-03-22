<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $course_titles = [
            'Python Junior', 'Introduction to Neural Networks', 'Industrial WEB-development',
            'Basics of Computer Vision', 'ROS2 Course'
        ];
        $lesson_contents = [
            'Prerequisites', 'Theory', 'Practice', 'Test', 'Conclusion'
        ];
        
        // Создаем курсы. Описание курса: 'THERE IS "<course_title>" DESCRIPTION.'
        for ($i = 0; $i < count($course_titles); $i++) {
            $course = new Course();
            $course->setTitle($course_titles[$i]);
            $course->setCode($i + 1);
            $course->setDescription('THERE IS "' . $course_titles[$i] . '" DESCRIPTION.');
            $manager->persist($course);
        }
        $manager->flush();  // Создаем курсы, чтобы были id для привязки уроков

        // Создаем уроки на основе названий курсов
        for ($i = 0; $i < count($course_titles); $i++) {
            $course = $manager->getRepository(Course::class)
                ->findOneBy(['title' => $course_titles[$i]]);
            if ($course) {
                for ($j = 0; $j < 5; $j++) {
                    $lesson = new Lesson();
                    $lesson->setCourse($course);
                    $lesson->setOrderNumber($j + 1);
                    $lesson->setTitle($course_titles[$i] . ': Lesson ' . $j + 1);
                    $lesson->setContent('In lesson ' . $j + 1 . ' you\'re gonna go through ' . $lesson_contents[$j]);
                    $manager->persist($lesson);
                }
            }
        }
        $manager->flush();  // Создаем уроки
    }
}

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
        $courseTitles = [
            'Python Junior', 'Introduction to Neural Networks', 'Industrial WEB-development',
            'Basics of Computer Vision', 'ROS2 Course'
        ];
        $lessonContents = [
            'Prerequisites', 'Theory', 'Practice', 'Test', 'Conclusion'
        ];

        // Создаем курсы. Описание курса: 'THERE IS "<course_title>" DESCRIPTION.'
        for ($i = 0; $i < count($courseTitles); $i++) {
            $course = new Course();
            $course->setTitle($courseTitles[$i]);
            $course->setCode($this->generateSlug($courseTitles[$i]));
            $course->setDescription('THERE IS "' . $courseTitles[$i] . '" DESCRIPTION.');
            $manager->persist($course);
        }
        $manager->flush();  // Создаем курсы, чтобы были id для привязки уроков

        // Создаем уроки на основе названий курсов
        for ($i = 0; $i < count($courseTitles); $i++) {
            $course = $manager->getRepository(Course::class)
                ->findOneBy(['title' => $courseTitles[$i]]);
            if ($course) {
                for ($j = 0; $j < 5; $j++) {
                    $lesson = new Lesson();
                    $lesson->setCourse($course);
                    $lesson->setOrderNumber($j + 1);
                    $lesson->setTitle($courseTitles[$i] . ': Lesson ' . $j + 1);
                    $lesson->setContent('In lesson ' . $j + 1 . ' you\'re gonna go through ' . $lessonContents[$j]);
                    $manager->persist($lesson);
                }
            }
        }
        $manager->flush();  // Создаем уроки
    }

    public function generateSlug(string $title): string
    {
        $slug = mb_strtolower($title, 'UTF-8');
        $slug = preg_replace('/[^\w\s-]+/u', '', $slug);
        $slug = preg_replace('/[\s_-]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
}

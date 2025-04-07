<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class LessonControllerTest extends WebTestCase
{
    public function testLessonsList(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));
        $course = $entityManager->getRepository(Course::class)->find($courseId);
        
        $shownCourseLessons = $crawler->filter('tr');
        $this->assertCount(count($course->getLessons()), $shownCourseLessons);
    }

    public function testShowExistingLesson(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();
    }

    public function testShowNonExistingLesson(): void
    {
        $client = static::createClient();

        $client->request('GET', '/lessons/9999999');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testNewLessonGet(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('a:contains("Create new lesson")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();
    }

    public function testNewLessonPostValidData(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('a:contains("Create new lesson")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));
        $course = $entityManager->getRepository(Course::class)->find($courseId);
        $courseLessonsBeforeCount = count($course->getLessons());

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();

        $form['lesson[title]'] = 'Test Title';
        $form['lesson[content]'] = 'Test Content';
        $form['lesson[orderNumber]'] = $courseLessonsBeforeCount + 1;
        $form['lesson[course]'] = $courseId;
        
        $client->submit($form);
        
        $this->assertResponseRedirects(
            '/courses/' . $courseId,
            Response::HTTP_SEE_OTHER
        );
        
        // Проверяем, что урок добавился
        $entityManager->clear();
        $updatedCourse = $entityManager->getRepository(Course::class)->find($courseId);
        $courseLessonsAfterCount = count($updatedCourse->getLessons());
        $this->assertEquals($courseLessonsBeforeCount + 1, $courseLessonsAfterCount);
        
        // Проверяем, что урок действительно создан
        $newLesson = $entityManager->getRepository(Lesson::class)
            ->findOneBy([
                'course' => $updatedCourse->getId(),
                'orderNumber' => $courseLessonsAfterCount
            ]);
        $this->assertNotNull($newLesson);
        $this->assertEquals('Test Title', $newLesson->getTitle());
        $this->assertEquals('Test Content', $newLesson->getContent());
    }

    public function testNewLessonPostEmptyTitle(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('a:contains("Create new lesson")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));
        $course = $entityManager->getRepository(Course::class)->find($courseId);
        $courseLessonsBeforeCount = count($course->getLessons());

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();

        $form['lesson[title]'] = '';
        $form['lesson[content]'] = 'Test Content';
        $form['lesson[orderNumber]'] = $courseLessonsBeforeCount + 1;
        $form['lesson[course]'] = $courseId;
        
        $client->submit($form);
        
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_title + .invalid-feedback',
            'Название урока не может быть пустым'
        );
    }

    public function testNewLessonPostLongTitle(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('a:contains("Create new lesson")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));
        $course = $entityManager->getRepository(Course::class)->find($courseId);
        $courseLessonsBeforeCount = count($course->getLessons());

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();

        $form['lesson[title]'] = str_repeat('TEST', 1000);
        $form['lesson[content]'] = 'Test Content';
        $form['lesson[orderNumber]'] = $courseLessonsBeforeCount + 1;
        $form['lesson[course]'] = $courseId;
        
        $client->submit($form);
        
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_title + .invalid-feedback',
            'Название урока не может быть длиннее 255 символов'
        );
    }

    public function testNewLessonPostEmptyContent(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('a:contains("Create new lesson")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));
        $course = $entityManager->getRepository(Course::class)->find($courseId);
        $courseLessonsBeforeCount = count($course->getLessons());

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();

        $form['lesson[title]'] = 'Test Title';
        $form['lesson[content]'] = '';
        $form['lesson[orderNumber]'] = $courseLessonsBeforeCount + 1;
        $form['lesson[course]'] = $courseId;
        
        $client->submit($form);
        
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_content + .invalid-feedback',
            'Содержание урока не может быть пустым'
        );
    }

    public function testNewLessonPostEmptyOrderNumber(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('a:contains("Create new lesson")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();

        $form['lesson[title]'] = 'Test Title';
        $form['lesson[content]'] = 'Test Content';
        $form['lesson[orderNumber]'] = '';
        $form['lesson[course]'] = $courseId;
        
        $client->submit($form);
        
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_orderNumber + .invalid-feedback',
            'Порядковый номер не может быть пустым'
        );
    }

    public function testNewLessonPostNegativeOrderNumber(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('a:contains("Create new lesson")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();

        $form['lesson[title]'] = 'Test Title';
        $form['lesson[content]'] = 'Test Content';
        $form['lesson[orderNumber]'] = -100;
        $form['lesson[course]'] = $courseId;
        
        $client->submit($form);
        
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_orderNumber + .invalid-feedback',
            'Порядковый номер должен быть от 1 до 10 000'
        );
    }

    public function testNewLessonPostLargeOrderNumber(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('a:contains("Create new lesson")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();

        $form['lesson[title]'] = 'Test Title';
        $form['lesson[content]'] = 'Test Content';
        $form['lesson[orderNumber]'] = 1000000;
        $form['lesson[course]'] = $courseId;
        
        $client->submit($form);
        
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_orderNumber + .invalid-feedback',
            'Порядковый номер должен быть от 1 до 10 000'
        );
    }

    public function testEditLessonGet(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();

        // На странице урока
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();
    }

    public function testEditLessonPostValidData(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();

        // На странице урока
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));

        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();

        $form['lesson[title]'] = 'TEST LESSON TITLE';
        $form['lesson[content]'] = 'TEST LESSON CONTENT';
        
        $client->submit($form);

        $this->assertResponseRedirects(
            '/courses/' . $courseId,
            Response::HTTP_SEE_OTHER
        );
        
        // Проверяем, что урок изменен
        $entityManager->clear();
        $updatedLesson = $entityManager->getRepository(Lesson::class)
            ->findOneBy([
                'course' => $courseId,
                'title' => 'TEST LESSON TITLE',
                'content' => 'TEST LESSON CONTENT'
            ]);
        $this->assertNotNull($updatedLesson);
    }

    public function testEditLessonPostEmptyTitle(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();

        // На странице урока
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();

        $form['lesson[title]'] = '';
        
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_title + .invalid-feedback',
            'Название урока не может быть пустым'
        );
    }

    public function testEditLessonPostLongTitle(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();

        // На странице урока
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();

        $form['lesson[title]'] = str_repeat('TEST', 1000);
        
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_title + .invalid-feedback',
            'Название урока не может быть длиннее 255 символов'
        );
    }

    public function testEditLessonPostEmptyContent(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();

        // На странице урока
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();

        $form['lesson[content]'] = '';
        
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_content + .invalid-feedback',
            'Содержание урока не может быть пустым'
        );
    }

    public function testEditLessonPostNegativeOrderNumber(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();

        // На странице урока
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();

        $form['lesson[orderNumber]'] = -100;
        
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_orderNumber + .invalid-feedback',
            'Порядковый номер должен быть от 1 до 10 000'
        );
    }

    public function testEditLessonPostLargeOrderNumber(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();

        // На странице урока
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();

        $form['lesson[orderNumber]'] = 1000000;
        
        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#lesson_orderNumber + .invalid-feedback',
            'Порядковый номер должен быть от 1 до 10 000'
        );
    }

    public function testDeleteLesson(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        $this->assertNotEquals(0, $crawler->filter('tr'));

        // Переход на страницу урока
        $firstLessonLink = $crawler->filter('tr td a')->first()->link();
        $crawler = $client->click($firstLessonLink);
        $this->assertResponseIsSuccessful();

        $courseId = basename(parse_url($firstCourseLink->getUri(), PHP_URL_PATH));
        $course = $entityManager->getRepository(Course::class)->find($courseId);
        $courseLessonsBeforeCount = count($course->getLessons());

        $form = $crawler->selectButton('Delete')->form();
        
        $client->submit($form);
        $this->assertResponseRedirects(
            '/courses/' . $courseId,
            Response::HTTP_SEE_OTHER
        );
        
        // Проверка, что урок удален
        $entityManager->clear();
        $updatedCourse = $entityManager->getRepository(Course::class)->find($courseId);
        $courseLessonsAfterCount = count(
            $updatedCourse->getLessons()
        );
        $this->assertEquals($courseLessonsBeforeCount - 1, $courseLessonsAfterCount);
    }
}

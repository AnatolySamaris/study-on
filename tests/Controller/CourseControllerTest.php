<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseControllerTest extends WebTestCase
{
    public function testCoursesList(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        $courses = $entityManager->getRepository(Course::class)->findAll();
        $shownCourses = $crawler->filter('table.table tbody tr');
        $this->assertCount(count($courses), $shownCourses);
    }

    public function testShowExistingCourse(): void
    {
        $client = static::createClient();
        
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();
    }

    public function testShowNonExistingCourse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/courses/999999');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testNewCourseGet(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();
    }

    public function testNewCoursePostValidData(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $coursesBefore = $entityManager->getRepository(Course::class)->findAll();
        $coursesBeforeCount = count($coursesBefore);
        
        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = 'Test Course';
        $form['course[description]'] = 'Test Description';
        
        $client->submit($form);
        $this->assertResponseRedirects('/courses', Response::HTTP_SEE_OTHER);
        
        // Проверяем, что курс добавился
        $coursesAfter = $entityManager->getRepository(Course::class)->findAll();
        $courseAfterCount = count($coursesAfter);
        $this->assertEquals($coursesBeforeCount + 1, $courseAfterCount);
        
        // Проверяем, что курс действительно создан
        $newCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => 'test-code-123']);
        $this->assertNotNull($newCourse);
        $this->assertEquals('Test Course', $newCourse->getTitle());
        $this->assertEquals('Test Description', $newCourse->getDescription());
    }

    public function testNewPostEmptyCode(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = '';
        $form['course[title]'] = 'Test Course';
        $form['course[description]'] = 'Test Description';
        
        $crawler = $client->submit($form);
        
        // Статус 422 (Unprocessable Content)
        $this->assertResponseStatusCodeSame(422);
        
        // - Сообщение об ошибке для поля code
        $this->assertSelectorTextContains(
            '#course_code + .invalid-feedback',
            'Код курса не может быть пустым'
        );
    }

    public function testNewPostNotUniqueCode(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Получаем какой-нибудь курс из бд
        $existingCourse = $entityManager->getRepository(Course::class)->findOneBy([]);

        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = $existingCourse->getCode();
        $form['course[title]'] = 'Test Course';
        $form['course[description]'] = 'Test Description';
        
        $crawler = $client->submit($form);
        
        // Статус 422 (Unprocessable Content)
        $this->assertResponseStatusCodeSame(422);
        
        // - Сообщение об ошибке для поля code
        $this->assertSelectorTextContains(
            '#course_code + .invalid-feedback',
            'Курс с таким кодом уже существует'
        );
    }

    public function testNewPostInvalidCode(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'Test Code+#/.,';
        $form['course[title]'] = 'Test Course';
        $form['course[description]'] = 'Test Description';
        
        $crawler = $client->submit($form);
        
        // Статус 422 (Unprocessable Content)
        $this->assertResponseStatusCodeSame(422);
        
        // - Сообщение об ошибке для поля code
        $this->assertSelectorTextContains(
            '#course_code + .invalid-feedback',
            'Код курса может содержать только буквы, цифры, дефисы и подчеркивания'
        );
    }

    public function testNewPostEmptyTitle(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = '';
        $form['course[description]'] = 'Test Description';
        
        $crawler = $client->submit($form);
        
        // Статус 422 (Unprocessable Content)
        $this->assertResponseStatusCodeSame(422);
        
        // - Сообщение об ошибке для поля code
        $this->assertSelectorTextContains(
            '#course_title + .invalid-feedback',
            'Название курса не может быть пустым'
        );
    }

    public function testNewPostLongTitle(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = str_repeat('TEST', 1000);
        $form['course[description]'] = 'Test Description';
        
        $crawler = $client->submit($form);
        
        // Статус 422 (Unprocessable Content)
        $this->assertResponseStatusCodeSame(422);
        
        // - Сообщение об ошибке для поля code
        $this->assertSelectorTextContains(
            '#course_title + .invalid-feedback',
            'Название курса не может быть длиннее 255 символов'
        );
    }

    public function testNewPostLongDescription(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = 'Test Title';
        $form['course[description]'] = str_repeat('TEST', 1000);
        
        $crawler = $client->submit($form);
        
        // Статус 422 (Unprocessable Content)
        $this->assertResponseStatusCodeSame(422);
        
        // - Сообщение об ошибке для поля code
        $this->assertSelectorTextContains(
            '#course_description + .invalid-feedback',
            'Описание не может быть длиннее 1000 символов'
        );
    }

    public function testEditGet(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        // На странице курса
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();
    }

    public function testEditPostWithValidData(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        // На странице курса
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        // На странице редактирования
        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();
        
        $courseCode = $form['course[code]']->getValue();    // Запоминаем, чтобы получить курс по коду

        $form['course[title]'] = 'TEST TITLE EDIT';
        $form['course[description]'] = 'TEST DESCRIPTION EDIT';
        
        $client->submit($form);
        $this->assertResponseRedirects('/courses', Response::HTTP_SEE_OTHER);
        
        // Проверка, что курс правда обновился
        $updatedCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertEquals('TEST TITLE EDIT', $updatedCourse->getTitle());
        $this->assertEquals('TEST DESCRIPTION EDIT', $updatedCourse->getDescription());
    }

    public function testEditPostEmptyCode(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        // На странице курса
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        // На странице редактирования
        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();
        
        $courseCode = $form['course[code]']->getValue();    // Запоминаем, чтобы получить курс по коду

        $form['course[code]'] = '';
        
        $client->submit($form);
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#course_code + .invalid-feedback',
            'Код курса не может быть пустым'
        );
        
        // Проверяем, что курс не изменился
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertNotNull($sameCourse);
    }

    public function testEditPostNotUniqueCode(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $lastCourseLink = $crawler->filter('table.table tbody tr td a')->last()->link();    // для кода
        $this->assertNotEquals($firstCourseLink, $lastCourseLink);

        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        // На странице курса
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        // На странице редактирования
        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();
        
        $courseCode = $form['course[code]']->getValue();    // Запоминаем, чтобы получить курс по коду

        // Получаем курс из бд с другим кодом
        $lastCourseId = basename(parse_url($lastCourseLink->getUri(), PHP_URL_PATH));
        $existingCourse = $entityManager->getRepository(Course::class)->find($lastCourseId);

        $this->assertNotEquals($courseCode, $existingCourse->getCode());

        $form['course[code]'] = $existingCourse->getCode();
        
        $client->submit($form);
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#course_code + .invalid-feedback',
            'Курс с таким кодом уже существует'
        );
        
        // Проверяем, что курс не изменился
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertNotNull($sameCourse);
    }

    public function testEditPostInvalidCode(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        // На странице курса
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        // На странице редактирования
        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();
        
        $courseCode = $form['course[code]']->getValue();    // Запоминаем, чтобы получить курс по коду

        $form['course[code]'] = 'test code+#./,';
        
        $client->submit($form);
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#course_code + .invalid-feedback',
            'Код курса может содержать только буквы, цифры, дефисы и подчеркивания'
        );
        
        // Проверяем, что курс не изменился
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertNotNull($sameCourse);
    }

    public function testEditPostEmptyTitle(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        // На странице курса
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        // На странице редактирования
        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();
        
        $courseCode = $form['course[code]']->getValue();    // Запоминаем, чтобы получить курс по коду
        $courseTitleBefore = $form['course[title]']->getValue();

        $form['course[title]'] = '';
        
        $client->submit($form);
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#course_title + .invalid-feedback',
            'Название курса не может быть пустым'
        );
        
        // Проверяем, что курс не изменился
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertEquals($courseTitleBefore, $sameCourse->getTitle());
    }

    public function testEditPostLongTitle(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        // На странице курса
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        // На странице редактирования
        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();
        
        $courseCode = $form['course[code]']->getValue();    // Запоминаем, чтобы получить курс по коду
        $courseTitleBefore = $form['course[title]']->getValue();

        $form['course[title]'] = str_repeat('TEST', 1000);
        
        $client->submit($form);
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#course_title + .invalid-feedback',
            'Название курса не может быть длиннее 255 символов'
        );
        
        // Проверяем, что курс не изменился
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertEquals($courseTitleBefore, $sameCourse->getTitle());
    }

    public function testEditPostLongDescription(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();

        // На странице курса
        $link = $crawler->filter('a:contains("Edit")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        // На странице редактирования
        $formButton = $crawler->selectButton('Update');
        $form = $formButton->form();
        
        $courseCode = $form['course[code]']->getValue();    // Запоминаем, чтобы получить курс по коду
        $courseDescriptionBefore = $form['course[description]']->getValue();

        $form['course[description]'] = str_repeat('TEST', 1000);
        
        $client->submit($form);
        $this->assertResponseStatusCodeSame(422);
        
        $this->assertSelectorTextContains(
            '#course_description + .invalid-feedback',
            'Описание не может быть длиннее 1000 символов'
        );
        
        // Проверяем, что курс не изменился
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertEquals($courseDescriptionBefore, $sameCourse->getDescription());
    }

    public function testDelete(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $coursesBeforeCount = count(
            $entityManager->getRepository(Course::class)->findAll()
        );

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        $firstCourseLink = $crawler->filter('table.table tbody tr td a')->first()->link();
        $crawler = $client->click($firstCourseLink);
        $this->assertResponseIsSuccessful();
        
        // На странице курса, жмем кнопку удалить
        $form = $crawler->selectButton('Delete')->form();
        
        $client->submit($form);
        $this->assertResponseRedirects('/courses', Response::HTTP_SEE_OTHER);
        
        $coursesAfterCount = count(
            $entityManager->getRepository(Course::class)->findAll()
        );
        $this->assertEquals($coursesBeforeCount - 1, $coursesAfterCount);
    }
}

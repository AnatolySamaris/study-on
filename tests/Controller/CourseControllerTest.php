<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Enum\CourseType;
use App\Service\BillingClient;
use App\Tests\Mock\BillingClientMock;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use function PHPUnit\Framework\assertStringContainsString;

class CourseControllerTest extends WebTestCase
{
    public function testCoursesList(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );
        
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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

        // Создание курса
        $link = $crawler->selectLink('Create new')->link();
        $crawler = $client->click($link);
        $client->followRedirects();
        $this->assertEquals('/courses/new', $client->getRequest()->getPathInfo());
        $this->assertResponseStatusCodeSame(200);
    }

    public function testNewCoursePostValidData(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $coursesBefore = $entityManager->getRepository(Course::class)->findAll();
        $coursesBeforeCount = count($coursesBefore);

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

        // Создание курса
        $link = $crawler->selectLink('Create new')->link();
        $crawler = $client->click($link);
        $this->assertEquals('/courses/new', $client->getRequest()->getPathInfo());

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = 'Test Course';
        $form['course[description]'] = 'Test Description';
        $form['course[type]'] = CourseType::RENT->value;
        $form['course[price]'] = 100.99;
        
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

        // Проверка на фронтенде
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        // TODO : Заработает после обработки добавления курса в биллинг
        // $shownCourses = $crawler->filter('table.table tbody tr');
        // $this->assertCount($courseAfterCount, $shownCourses);
    }

    public function testNewPostEmptyCode(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = '';
        $form['course[title]'] = 'Test Course';
        $form['course[description]'] = 'Test Description';
        $form['course[type]'] = CourseType::RENT->value;
        $form['course[price]'] = 100.99;
        
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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

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
        $form['course[type]'] = CourseType::RENT->value;
        $form['course[price]'] = 100.99;
        
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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'Test Code+#/.,';
        $form['course[title]'] = 'Test Course';
        $form['course[description]'] = 'Test Description';
        $form['course[type]'] = CourseType::RENT->value;
        $form['course[price]'] = 100.99;
        
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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = '';
        $form['course[description]'] = 'Test Description';
        $form['course[type]'] = CourseType::RENT->value;
        $form['course[price]'] = 100.99;
        
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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = str_repeat('TEST', 1000);
        $form['course[description]'] = 'Test Description';
        $form['course[type]'] = CourseType::RENT->value;
        $form['course[price]'] = 100.99;
        
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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = 'Test Title';
        $form['course[description]'] = str_repeat('TEST', 1000);
        $form['course[type]'] = CourseType::RENT->value;
        $form['course[price]'] = 100.99;
        
        $crawler = $client->submit($form);
        
        // Статус 422 (Unprocessable Content)
        $this->assertResponseStatusCodeSame(422);
        
        // - Сообщение об ошибке для поля code
        $this->assertSelectorTextContains(
            '#course_description + .invalid-feedback',
            'Описание не может быть длиннее 1000 символов'
        );
    }

    public function testNewPostInvalidType(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        $crawler = $client->request('GET', '/courses');

        $link = $crawler->filter('a:contains("Create new")')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();

        $formButton = $crawler->selectButton('Save');
        $form = $formButton->form();
        
        $form['course[code]'] = 'test-code-123';
        $form['course[title]'] = 'Test Title';
        $form['course[description]'] = 'Test Description';
        $form['course[type]'] = CourseType::RENT->value;
        $form['course[price]'] = -10;
        
        $crawler = $client->submit($form);
        
        // Статус 422 (Unprocessable Content)
        $this->assertResponseStatusCodeSame(422);
        
        // - Сообщение об ошибке для поля code
        $this->assertSelectorTextContains(
            '.invalid-feedback',
            'Значение должно быть неотрицательным'
        );
    }

    public function testEditGet(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);

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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Для проверки что количество не поменялось после редактирования
        $coursesCount = count($entityManager->getRepository(Course::class)->findAll());
        
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

        // Проверка на фронтенде, что число курсов не поменялось
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        $shownCourses = $crawler->filter('table.table tbody tr');
        $this->assertCount($coursesCount, $shownCourses);
    }

    public function testEditPostEmptyCode(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);

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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);

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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);

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
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);

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
        $entityManager->clear();
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertEquals($courseTitleBefore, $sameCourse->getTitle());
    }

    public function testEditPostLongTitle(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);

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
        $entityManager->clear();
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertEquals($courseTitleBefore, $sameCourse->getTitle());
    }

    public function testEditPostLongDescription(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);

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
        $entityManager->clear();
        $sameCourse = $entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
        $this->assertEquals($courseDescriptionBefore, $sameCourse->getDescription());
    }

    public function testDelete(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'admin@mail.ru', # Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($login);
        
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

    public function testGuestUserAccessNewCourseGet(): void
    {
        $client = static::createClient();

        // Переходим напрямую на создание курса
        $crawler = $client->request('GET', 'courses/new');

        $this->assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();

        // Проверяем, что оказались на странице логина
        $this->assertEquals('/login', $client->getRequest()->getPathInfo());
    }

    public function testGuestUserAccessEditCourseGet(): void
    {
        $client = static::createClient();

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $course = $entityManager->getRepository(Course::class)->findOneBy(['title' => 'Python Junior']);

        // Переходим напрямую на редактирование курса
        $crawler = $client->request('GET', 'courses/' . $course->getId() . '/edit');

        $this->assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();

        // Проверяем, что оказались на странице логина
        $this->assertEquals('/login', $client->getRequest()->getPathInfo());
    }

    public function testAccessDeniedNewCourseGet(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'user@mail.ru', # Логинимся как юзер
            'password' => 'password',
        ]);
        $client->submit($login);

        // Переходим напрямую на создание курса
        $crawler = $client->request('GET', 'courses/new');

        // Проверяем, что поймали исключение Access Denied
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAccessDeniedEditCourseGet(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'user@mail.ru', # Логинимся как юзер
            'password' => 'password',
        ]);
        $client->submit($login);

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $course = $entityManager->getRepository(Course::class)->findOneBy(['title' => 'Python Junior']);

        // Переходим напрямую на редактирование курса
        $crawler = $client->request('GET', 'courses/' . $course->getId() . '/edit');

        // Проверяем, что поймали исключение Access Denied
        $this->assertResponseStatusCodeSame(403);
    }

    public function testBuyPayCourseSuccessful(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'user@mail.ru', # Логинимся как юзер
            'password' => 'password',
        ]);
        $client->submit($login);

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Выбираем покупаемый курс
        $course = $entityManager->getRepository(Course::class)->findOneBy(['title' => 'Basics of Computer Vision']);

        // Покупаем курс
        $client->request('POST', '/courses/' . $course->getId() . '/pay');
        $client->followRedirect();

        // Проверка, что покупка успешная
        $crawler = $client->getCrawler();
        $this->assertSelectorTextContains('.alert-success', 'Course successfully paid!');
        $this->assertStringContainsString(
            'Course is available',
            $crawler->filter('h3')->text()
        );

        // Проверяем наличие новой транзакции
        $crawler = $client->request('GET', '/transactions');
        $this->assertResponseIsSuccessful();

        $shownTransactions = $crawler->filter('table.table tbody tr');
        $this->assertCount(3, $shownTransactions);  // Ожидаем 3 транзакции

        $rows = $crawler->filter('table.table tbody tr');
        $this->assertStringContainsString(
            'payment',  // Тип транзакции
            $rows->first()->filter('td')->eq(1)->text(),
        );
        $link = $rows->first()->filter('td a')->link();   // Ссылка на купленный курс
        $this->assertStringContainsString(
            '/courses/' . $course->getId(),
            $link->getUri(),
        );
    }

    public function testBuyRentCourseSuccessful(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'user@mail.ru', # Логинимся как юзер
            'password' => 'password',
        ]);
        $client->submit($login);

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Выбираем покупаемый курс
        $course = $entityManager->getRepository(Course::class)->findOneBy(['title' => 'Python Junior']);

        // Покупаем курс
        $client->request('POST', '/courses/' . $course->getId() . '/pay');
        $client->followRedirect();

        // Проверка, что покупка успешная
        $crawler = $client->getCrawler();
        $now = new DateTime();
        $this->assertSelectorTextContains('.alert-success', 'Course successfully paid!');
        $this->assertStringContainsString(
            'Course is available until ' . $now->modify('+1 week')->format('d.m.Y'),
            $crawler->filter('h3')->text()
        );

        // Проверяем наличие новой транзакции
        $crawler = $client->request('GET', '/transactions');
        $this->assertResponseIsSuccessful();

        $shownTransactions = $crawler->filter('table.table tbody tr');
        $this->assertCount(3, $shownTransactions);  // Ожидаем 3 транзакции

        $rows = $crawler->filter('table.table tbody tr');
        $this->assertStringContainsString(
            'payment',  // Тип транзакции
            $rows->first()->filter('td')->eq(1)->text(),
        );
        $link = $rows->first()->filter('td a')->link();   // Ссылка на купленный курс
        $this->assertStringContainsString(
            '/courses/' . $course->getId(),
            $link->getUri(),
        );
    }

    public function testBuyFreeCourseSuccessful(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'user@mail.ru', # Логинимся как юзер
            'password' => 'password',
        ]);
        $client->submit($login);

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Выбираем покупаемый курс
        $course = $entityManager->getRepository(Course::class)->findOneBy(['title' => 'ROS2 Course']);

        // Покупаем курс
        $client->request('POST', '/courses/' . $course->getId() . '/pay');
        $client->followRedirect();

        // Проверка, что покупка успешная
        $crawler = $client->getCrawler();
        $this->assertSelectorTextContains('.alert-success', 'Course successfully paid!');
        $this->assertStringContainsString(
            'Course is available',
            $crawler->filter('h3')->text()
        );

        // Проверяем наличие новой транзакции
        $crawler = $client->request('GET', '/transactions');
        $this->assertResponseIsSuccessful();

        $shownTransactions = $crawler->filter('table.table tbody tr');
        $this->assertCount(3, $shownTransactions);  // Ожидаем 3 транзакции

        $rows = $crawler->filter('table.table tbody tr');
        $this->assertStringContainsString(
            'payment',  // Тип транзакции
            $rows->first()->filter('td')->eq(1)->text(),
        );
        $link = $rows->first()->filter('td a')->link();   // Ссылка на купленный курс
        $this->assertStringContainsString(
            '/courses/' . $course->getId(),
            $link->getUri(),
        );
    }

    // Для случая, когда сделан запрос напрямую по url
    public function testBuyCourseNotEnoughBalance(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'new_user@mail.ru', # Логинимся как новый юзер (без денег)
            'password' => 'password',
        ]);
        $client->submit($login);

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Выбираем покупаемый курс
        $course = $entityManager->getRepository(Course::class)->findOneBy(['title' => 'Basics of Computer Vision']);

        // Покупаем курс
        $client->request('POST', '/courses/' . $course->getId() . '/pay');
        $client->followRedirect();

        // Проверка, что покупка успешная
        $crawler = $client->getCrawler();
        $this->assertSelectorTextContains('.alert-danger', 'Not enough money for payment.');

        // Проверяем что число транзакций не изменилось
        $crawler = $client->request('GET', '/transactions');
        $this->assertResponseIsSuccessful();

        $shownTransactions = $crawler->filter('table.table tbody tr');
        $this->assertCount(1, $shownTransactions);  // Ожидаем 1 транзакцию
    }

    public function testBuyCourseNotEnoughBalanceButtonDisabled(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        // Логин
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'new_user@mail.ru', # Логинимся как новый юзер (без денег)
            'password' => 'password',
        ]);
        $client->submit($login);

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Выбираем покупаемый курс
        $course = $entityManager->getRepository(Course::class)->findOneBy(['title' => 'Basics of Computer Vision']);

        // Страница курса
        $crawler = $client->request('GET', '/courses/' . $course->getId());
        $this->assertResponseIsSuccessful();

        // Проверяем, что кнопка покупки заблокирована
        $buyButton = $crawler->selectButton('Buy Course');
        $this->assertNotNull(
            $buyButton->attr('disabled'),
        );
    }
}

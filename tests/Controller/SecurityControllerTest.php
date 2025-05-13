<?php

namespace App\Tests\Controller;

use App\Service\BillingClient;
use App\Tests\Mock\BillingClientMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginSuccessful(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        // Логинимся
        $authFormLink = $crawler->selectLink('Log In')->link();
        $crawler = $client->click($authFormLink);
        $this->assertEquals('/login', $client->getRequest()->getPathInfo());

        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'user@mail.ru',
            'password' => 'password',
        ]);
        $client->submit($login);

        // Проверяем что произошел редирект на страницу курсов и появилась кнопка Профиль (=> логин произошел)
        $this->assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();
        $this->assertEquals('/courses', $client->getRequest()->getPathInfo());
        $this->assertEquals(1, $crawler->selectLink('Profile')->count());
    }

    public function testLoginUnexistingEmail(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        // Логинимся
        $authFormLink = $crawler->selectLink('Log In')->link();
        $crawler = $client->click($authFormLink);
        $this->assertEquals('/login', $client->getRequest()->getPathInfo());

        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'UNEXISTING@mail.ru',
            'password' => 'password',
        ]);
        $client->submit($login);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains(
            '.alert.alert-danger',
            'Authentication error: User with given username not found'
        );
    }

    public function testLoginInvalidPassword(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        // Логинимся
        $authFormLink = $crawler->selectLink('Log In')->link();
        $crawler = $client->click($authFormLink);
        $this->assertEquals('/login', $client->getRequest()->getPathInfo());

        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'user@mail.ru',
            'password' => 'INVALID PASSWORD',
        ]);
        $client->submit($login);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains(
            '.alert.alert-danger',
            'Authentication error: Invalid password'
        );
    }

    public function testRegisterSuccessful(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        // Регистрация
        $authFormLink = $crawler->selectLink('Register')->link();
        $crawler = $client->click($authFormLink);
        $this->assertEquals('/register', $client->getRequest()->getPathInfo());

        $reg = $crawler->selectButton('Register')->form([
            'registration_form[email]' => "new_user@mail.ru",
            'registration_form[password][first]' => 'password',
            'registration_form[password][second]' => 'password',
        ]);
        $client->submit($reg);

        // Проверяем что произошел редирект на страницу курсов и появилась кнопка Профиль (=> регистрация произошла)
        $this->assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();
        $this->assertEquals('/courses', $client->getRequest()->getPathInfo());
        $this->assertEquals(1, $crawler->selectLink('Profile')->count());
    }

    public function testRegisterExistingUser(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        // Регистрация
        $authFormLink = $crawler->selectLink('Register')->link();
        $crawler = $client->click($authFormLink);
        $this->assertEquals('/register', $client->getRequest()->getPathInfo());

        $reg = $crawler->selectButton('Register')->form([
            'registration_form[email]' => "user@mail.ru",
            'registration_form[password][first]' => 'password',
            'registration_form[password][second]' => 'password',
        ]);
        $client->submit($reg);

        self::assertSelectorTextContains(
            '.alert.alert-danger',
            'User with this email already exists'
        );
    }

    public function testRegisterLoggedInUser(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        // Сначала логинимся
        $authFormLink = $crawler->selectLink('Log In')->link();
        $crawler = $client->click($authFormLink);
        $this->assertEquals('/login', $client->getRequest()->getPathInfo());

        $submitBtn = $crawler->selectButton('Sign in');
        $login = $submitBtn->form([
            'email' => 'user@mail.ru',
            'password' => 'password',
        ]);
        $client->submit($login);
        $this->assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();
        $this->assertEquals('/courses', $client->getRequest()->getPathInfo());
        
        // Переходим напрямую на страницу регистрации
        $crawler = $client->request('GET', '/register');
        $this->assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();

        // Проверяем, что оказались в профиле
        $this->assertEquals('/profile', $client->getRequest()->getPathInfo());
        $this->assertSelectorTextContains('.username', 'user@mail.ru');
        $this->assertSelectorTextContains('.balance', '1259.99');
        $this->assertSelectorTextContains('.role', 'User');
    }

    public function testRegisterUnmatchingPasswords(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        
        // Регистрация
        $authFormLink = $crawler->selectLink('Register')->link();
        $crawler = $client->click($authFormLink);
        $this->assertEquals('/register', $client->getRequest()->getPathInfo());

        $reg = $crawler->selectButton('Register')->form([
            'registration_form[email]' => "new_user@mail.ru",
            'registration_form[password][first]' => 'password',
            'registration_form[password][second]' => 'PASSWORD',
        ]);
        $client->submit($reg);

        self::assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Passwords must match'
        );
    }
}

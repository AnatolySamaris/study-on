<?php

namespace App\Tests\Controller;

use App\Service\BillingClient;
use App\Tests\Mock\BillingClientMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileControllerTest extends WebTestCase
{
    public function testProfileGetLoggedIn(): void
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
        
        // Переходим в профиль
        $link = $crawler->selectLink('Profile')->link();
        $crawler = $client->click($link);
        $this->assertEquals('/profile', $client->getRequest()->getPathInfo());
        $this->assertResponseIsSuccessful();

        // Проверяем данные профиля
        $this->assertSelectorTextContains('.username', 'user@mail.ru');
        $this->assertSelectorTextContains('.balance', '1259.99');
        $this->assertSelectorTextContains('.role', 'User');
    }

    public function testLogout(): void
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

        // Проверяем что на главной странице нет кнопок логина и регистрации
        $this->assertEquals(0, $crawler->selectLink('Log In')->count());
        $this->assertEquals(0, $crawler->selectLink('Register')->count());
        
        // Переходим в профиль
        $link = $crawler->selectLink('Profile')->link();
        $crawler = $client->click($link);
        $this->assertEquals('/profile', $client->getRequest()->getPathInfo());

        // Разлогиниваемся
        $link = $crawler->selectLink('Logout')->link();
        $crawler = $client->click($link);
        $this->assertEquals('/logout', $client->getRequest()->getPathInfo());
        $crawler = $client->followRedirect();
        $this->assertEquals('/', $client->getRequest()->getPathInfo());
        $crawler = $client->followRedirect();

        // Проверка что появились кнопки логина и регистрации
        $this->assertEquals('/courses', $client->getRequest()->getPathInfo());
        $this->assertEquals(1, $crawler->selectLink('Log In')->count());
        $this->assertEquals(1, $crawler->selectLink('Register')->count());
    }

    public function testPermissionDeniedProfile(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );
        
        // Переходим в профиль напрямую
        $crawler = $client->request('GET', '/profile');
        $this->assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();

        // Проверяем, что оказались на странице логина
        $this->assertEquals('/login', $client->getRequest()->getPathInfo());
    }

    public function testTransactionsList(): void
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

        // Проверяем что на главной странице нет кнопок логина и регистрации
        $this->assertEquals(0, $crawler->selectLink('Log In')->count());
        $this->assertEquals(0, $crawler->selectLink('Register')->count());
        
        // Переходим в профиль
        $link = $crawler->selectLink('Profile')->link();
        $crawler = $client->click($link);
        $this->assertEquals('/profile', $client->getRequest()->getPathInfo());

        // Переходим на страницу истории транзакций
        $link = $crawler->selectLink('Transactions History')->link();
        $crawler = $client->click($link);
        $this->assertEquals('/transactions', $client->getRequest()->getPathInfo());

        // Проверка, что транзакций правильное количество
        $shownTransactions = $crawler->filter('table.table tbody tr');
        $this->assertCount(2, $shownTransactions);  // Ожидаем 2 транзакции

        // Проверка, что последняя транзакция - покупка курса
        $rows = $crawler->filter('table.table tbody tr');

        $this->assertStringContainsString(
            'payment',  // Тип транзакции
            $rows->last()->filter('td')->eq(1)->text(),
        );
        $this->assertStringContainsString(
            '0',    // Величина транзакции (0 => бесплатный)
            $rows->last()->filter('td')->eq(2)->text(),
        );
        $link = $rows->last()->filter('td a')->link();   // Ссылка на купленный курс
        $this->assertStringContainsString(
            '/courses/',
            $link->getUri(),
        );

        // Проверка, что первая транзакция - пополнение на верную сумму
        $this->assertStringContainsString(
            'deposit',  // Тип транзакции
            $rows->first()->filter('td')->eq(1)->text(),
        );
        $this->assertStringContainsString(
            '1259.99',    // Величина транзакции (0 => бесплатный)
            $rows->first()->filter('td')->eq(2)->text(),
        );
        $this->assertStringContainsString(
            '-',    // Отсутствует ссылка
            $rows->first()->filter('td')->eq(3)->text(),
        );
    }

    public function testGuestAccessTransactionsList(): void
    {
        $client = $this::createClient();
        $client->disableReboot();
        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock()
        );
        
        // Переходим в профиль напрямую
        $crawler = $client->request('GET', '/transactions');
        $this->assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();

        // Проверяем, что оказались на странице логина
        $this->assertEquals('/login', $client->getRequest()->getPathInfo());
    }
}

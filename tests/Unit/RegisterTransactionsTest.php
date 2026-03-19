<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Lib\ListmonkClient;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Legal\LegalGateway;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Register\RegisterGateway;
use Foodsharing\Modules\Register\RegisterTransactions;
use Foodsharing\Utility\EmailHelper;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Support\UnitTester;

class RegisterTransactionsTest extends Unit
{
    protected UnitTester $tester;
    private RegisterGateway $registerGateway;
    private FoodsaverGateway $foodsaverGateway;
    private RegisterTransactions $registerTransactions;
    private Generator $faker;

    public function _before(): void
    {
        $this->faker = Factory::create('de_DE');

        $this->foodsaverGateway = $this->tester->get(FoodsaverGateway::class);
        $this->registerGateway = $this->tester->get(RegisterGateway::class);
        $this->registerTransactions = new RegisterTransactions(
            $this->createMock(LoginGateway::class),
            $this->tester->get(EmailHelper::class),
            $this->tester->get(TranslatorInterface::class),
            $this->createMock(LegalGateway::class),
            $this->registerGateway,
            $this->foodsaverGateway,
            $this->tester->get(ListmonkClient::class),
        );
    }

    public function testNewRegistration(): void
    {
        $email = $this->faker->email();
        $this->registerTransactions->addRegistrationAttempt($email);
        $this->assertTrue($this->registerGateway->doesRegistrationAttemptExist($email));
        $this->tester->expectNumMails(1);
        $email = $this->tester->getMails()[0];
        $this->tester->assertStringContainsString('unter folgendem Link fortsetzen', $email->html);
    }

    public function testOngoingRegistration(): void
    {
        $email = $this->faker->email();
        $this->registerGateway->addRegistrationAttempt($email, null);
        $this->assertTrue($this->registerGateway->doesRegistrationAttemptExist($email));
        $this->expectException(AccessDeniedHttpException::class);
        $this->registerTransactions->addRegistrationAttempt($email);
        $this->tester->expectNumMails(0);
    }

    public function testRegistrationWithExistingEmail(): void
    {
        $email = $this->faker->email();

        $this->tester->haveInDatabase('fs_foodsaver', [
            'rolle' => 0,
            'active' => 1,
            'email' => $email,
            'password' => null,
            'name' => null,
            'nachname' => null,
            'geb_datum' => null,
            'handy' => null,
            'newsletter' => 0,
            'geschlecht' => 0,
            'anmeldedatum' => null,
            'token' => '',
        ]);

        $this->registerTransactions->addRegistrationAttempt($email);
        $this->tester->expectNumMails(1);
        $email = $this->tester->getMails()[0];
        $this->tester->assertStringContainsString('jemand hat versucht', $email->html);
    }
}

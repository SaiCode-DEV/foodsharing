<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class PasswordResetCest
{
    final public function testPasswordReset(AcceptanceTester $I): void
    {
        $I->wantTo('do a password reset');

        $newPass = 'TEEEEST';

        $user = $I->createFoodsaver();

        $I->amOnPage('/');
        $I->see('Einloggen', ['css' => '.testing-login-dropdown']);
        $I->click('.testing-login-dropdown > .nav-link');
        $I->waitForText('Passwort vergessen?');
        $I->click('.testing-login-click-password-reset');

        $I->see('Gib deine E-Mail-Adresse ein');
        $I->fillField('#email', $user['email']);
        $I->click('Senden');

        $I->see('Alles klar, dir wurde ein Link zum Ändern des Passworts per E-Mail zugeschickt');

        // receive a mail
        $I->expectNumMails(1, 5);
        $mail = $I->getMails()[0];

        $I->assertEquals($mail->headers->to, $user['email'], 'correct recipient');

        // Use text content instead of HTML and update regex
        $pattern = '/http:\/\/[^\/]+\/+login\?sub=passwordReset&k=[a-f0-9]+/';
        $I->assertRegExp($pattern, $mail->text, 'mail should contain a link');
        preg_match($pattern, $mail->text, $matches);
        $link = $matches[0];

        // Clean up the URL by removing any potential double slashes (except after http:)
        $link = preg_replace('#(?<!:)//+#', '/', $link);

        // Strip any full URL if present and keep only the path
        if (str_starts_with((string)$link, 'http')) {
            $link = parse_url((string)$link, PHP_URL_PATH) . '?' . parse_url((string)$link, PHP_URL_QUERY);
        }

        // go to link in the mail
        $I->amOnPage($link);
        $I->waitForElement('#pass1');

        // First attempt with invalid password
        $I->fillField('#pass1', $newPass);
        $I->fillField('#pass2', 'INVALID');
        $I->click('input[type=submit]');
        // ToDo: Disabled - can enabled after refactoring to vue js
        // $I->waitForText('Sorry, die Passwörter stimmen nicht überein.');

        // Final attempt with valid password
        $I->fillField('#pass1', $newPass);
        $I->fillField('#pass2', $newPass);
        $I->click('input[type=submit]');

        $I->seeCurrentUrlEquals('/login');

        // password got replaced after login
        $I->seeInDatabase('fs_foodsaver', [
            'email' => $user['email']
        ]);

        // new hash is valid
        $newHash = $I->grabFromDatabase('fs_foodsaver', 'password', ['email' => $user['email']]);
        $I->assertTrue(password_verify($newPass, (string)$newHash));
    }
}

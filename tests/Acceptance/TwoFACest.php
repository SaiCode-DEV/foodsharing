<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use Tests\Support\AcceptanceTester;

class TwoFACest
{
    private $pass;
    private $foodsaver;
    private $secret;
    private $backupCodes;

    public function _before(AcceptanceTester $I): void
    {
        $this->pass = sq('pass');
        $this->foodsaver = $I->createFoodsharer($this->pass);
        $this->secret = '';
        $this->backupCodes = [];
    }

    private function enableTOTP(AcceptanceTester $I): void
    {
        // Assert in database that TOTP is not enabled
        $I->seeInDatabase('fs_foodsaver', [
            'email' => $this->foodsaver['email'],
            'totp_secret' => null,
            'backup_codes' => null,
        ]);

        // Login without TOTP
        $I->login($this->foodsaver['email'], $this->pass);

        // Go to settings
        $I->amOnPage('/user/current/settings');
        $I->waitForPageBody();
        $I->waitForActiveAPICalls();
        $I->click('Zwei-Faktor-Authentisierung');
        $I->waitForElement('.testing-totp-enable');

        // Click on enable TOTP
        $I->click('.testing-totp-enable');
        $I->waitForActiveAPICalls();

        // Check all expected elements are loaded
        $I->waitForElement('.testing-qr-code');
        $I->waitForElement('.testing-totp-secret');
        $I->waitForElement('.testing-backup-codes');

        // Get the secret and backup codes
        $this->secret = $I->grabTextFrom('.testing-totp-secret');
        $this->backupCodes = $I->grabMultiple('.testing-backup-codes');

        // Assert the secret and backup codes are not empty
        $I->assertNotEmpty($this->secret, 'Secret is not empty');
        foreach ($this->backupCodes as $code) {
            $I->assertNotEmpty($code, 'None of the backup codes is empty');
        }

        // Check the secret has the expected format (Base32, 32 characters)
        $I->assertRegExp('/^[A-Z2-7]{32}$/', $this->secret, 'Secret has the expected Base32 format');

        // Assert there are twelve backup codes
        $I->assertCount(12, $this->backupCodes, 'There are twelve backup codes');

        // Compute valid code
        $qrCodeProvider = new BaconQrCodeProvider();
        $tfa = new TwoFactorAuth($qrCodeProvider);
        $validCode = $tfa->getCode($this->secret);

        // Fill the form with the valid code
        $I->fillField('#testing-totp-input-totp > input', $validCode);
        $I->fillField('#testing-totp-input-password > input', $this->pass);

        // Click on submit
        $I->click('Aktivieren');
        $I->waitForActiveAPICalls();

        // Check we have been redirected to the login page, we do this
        // indirectly by checking the URL as it may contain a redirecton target
        $url = $I->grabFromCurrentUrl();
        $I->assertStringContainsString('/login', $url, 'We have been redirected to the login page');

        // Assert in database that TOTP is enabled
        $I->seeInDatabase('fs_foodsaver', [
            'email' => $this->foodsaver['email'],
            'totp_secret' => $this->secret,
            'backup_codes' => json_encode($this->backupCodes),
        ]);
    }

    public function testTOTPActivate(AcceptanceTester $I): void
    {
        $I->wantTo('Activate TOTP');

        // Enable TOTP
        $this->enableTOTP($I);
    }

    public function testTOTPLoginNoCode(AcceptanceTester $I): void
    {
        $I->wantTo('Login without code fails when TOTP is enabled');

        // Enable TOTP
        $this->enableTOTP($I);

        // Assert login without TOTP fails
        $I->login($this->foodsaver['email'], $this->pass, null, true);
    }

    public function testTOTPLoginWithCode(AcceptanceTester $I): void
    {
        $I->wantTo('Login with code succeeds when TOTP is enabled');

        // Enable TOTP
        $this->enableTOTP($I);

        // Compute valid code
        $qrCodeProvider = new BaconQrCodeProvider();
        $tfa = new TwoFactorAuth($qrCodeProvider);
        $validCode = $tfa->getCode($this->secret);

        // Assert login with TOTP succeeds
        $I->login($this->foodsaver['email'], $this->pass, $validCode, false);
    }

    public function testTOTPLoginWithBackupCode(AcceptanceTester $I): void
    {
        $I->wantTo('Login with backup code succeeds when TOTP is enabled');

        // Enable TOTP
        $this->enableTOTP($I);

        // Assert login with backup code succeeds
        $I->login($this->foodsaver['email'], $this->pass, $this->backupCodes[0], false);

        // Go to settings (test direct navigation via GET parameter)
        $I->amOnPage('/user/current/settings?sub=change2FA');
        $I->waitForPageBody();
        $I->waitForActiveAPICalls();

        // Assert 11 backup codes remain
        $I->waitForText('11', 10, '#testing-num-backup-codes');
    }

    public function testTOTPenable_disable(AcceptanceTester $I): void
    {
        $I->wantTo('Enable and disable TOTP');

        // Enable TOTP
        $this->enableTOTP($I);

        // Compute valid code
        $qrCodeProvider = new BaconQrCodeProvider();
        $tfa = new TwoFactorAuth($qrCodeProvider);
        $validCode = $tfa->getCode($this->secret);

        // Login with TOTP
        $I->login($this->foodsaver['email'], $this->pass, $validCode, false);

        // Go to settings
        $I->amOnPage('/user/current/settings');
        $I->waitForPageBody();
        $I->waitForActiveAPICalls();
        $I->click('Zwei-Faktor-Authentisierung');
        $I->waitForElement('.testing-totp-disable');

        // Click on disable TOTP
        $I->click('.testing-totp-disable');
        $I->waitForActiveAPICalls();

        // Fill the form with the valid code
        $validCode = $tfa->getCode($this->secret);
        $I->fillField('#testing-totp-input-totp > input', $validCode);
        $I->fillField('#testing-totp-input-password > input', $this->pass);

        // Click on submit
        $I->click('Deaktivieren');
        $I->waitForActiveAPICalls();

        // Check we have been redirected to the login page, we do this
        // indirectly by checking the URL as it may contain a redirecton target
        $url = $I->grabFromCurrentUrl();
        $I->assertStringContainsString('/login', $url, 'We have been redirected to the login page');

        // Assert in database that TOTP is not enabled
        $I->seeInDatabase('fs_foodsaver', [
            'email' => $this->foodsaver['email'],
            'totp_secret' => null,
            'backup_codes' => null,
        ]);
    }

    public function passwordResetEnforcesTOTP(AcceptanceTester $I): void
    {
        $I->wantTo('Reset password enforces TOTP');
        $newPass = 'yourNewPassword1234!';

        // Enable TOTP
        $this->enableTOTP($I);

        $I->amOnPage('/');
        $I->see('Einloggen', ['css' => '.testing-login-dropdown']);
        $I->click('.testing-login-dropdown > .nav-link');
        $I->waitForText('Passwort vergessen?');
        $I->click('.testing-login-click-password-reset');

        $I->see('Gib deine E-Mail-Adresse ein');
        $I->fillField('#email', $this->foodsaver['email']);
        $I->click('Senden');

        $I->waitForText('Alles klar, dir wurde ein Link zum Ändern des Passworts per E-Mail zugeschickt');

        // receive a mail
        $I->expectNumMails(1, 5);
        $mail = $I->getMails()[0];

        $I->assertEquals($mail->headers->to, $this->foodsaver['email'], 'correct recipient');

        // Use text content instead of HTML and update regex
        $pattern = '/http:\/\/[^\/]+\/+password-reset\/[a-f0-9]+\?totp=true/';
        $I->assertRegExp($pattern, $mail->text, 'mail should contain a link with TOTP property');
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
        $I->waitForPageBody();
        $I->waitForActiveAPICalls();

        // Compute a valid code
        $qrCodeProvider = new BaconQrCodeProvider();
        $tfa = new TwoFactorAuth($qrCodeProvider);
        $validCode = $tfa->getCode($this->secret);

        // Fill the form
        $I->fillField('#testing-reset-input-password > input', $newPass);
        $I->fillField('#testing-reset-input-confirm-password > input', $newPass);
        $I->fillField('#testing-reset-input-totp > input', $validCode);
        $I->click('#password-change-button');
        $I->waitForText('Prima, dein Passwort wurde erfolgreich geändert.');

        // Ensure new password hash is set in database -> change password worked
        $newHash = $I->grabFromDatabase('fs_foodsaver', 'password', ['email' => $this->foodsaver['email']]);
        $I->assertTrue(password_verify($newPass, (string)$newHash));
    }
}

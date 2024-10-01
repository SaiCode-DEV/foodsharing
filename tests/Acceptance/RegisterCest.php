<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class RegisterCest
{
    private $email;
    private $stripped_email;
    private $first_name;
    private $last_name;
    private $password;
    private $mobile_number;

    public function _before(): void
    {
        $this->email = '     ' . sq('email') . '@test.com      ';
        $this->stripped_email = sq('email') . '@test.com';
        $this->first_name = sq('first_name');
        $this->last_name = sq('last_name');
        $this->password = sq('password');
        $this->birthdateUSFormat = '1983-08-27';
        $this->mobile_number = '177 3231323';
        $this->mobile_country_code = '+49 ';
    }

    public function canRegisterNewUserWithNewsletter(AcceptanceTester $I): void
    {
        // create some unique values for our new user

        $I->wantTo('ensure I can register and will receive newsletter by default');
        $I->amOnPage('/');

        // click signup, then press next on the first dialog

        $I->click('.testing-register-link');
        $I->click('Jetzt registrieren');
        $I->waitForElementVisible('#step1', 4);
        $I->fillField('#email', $this->email);
        $I->fillField('#password', $this->password);
        $I->fillField('#confirmPassword', $this->password);
        $I->click('weiter');

        // fill in basic details

        $I->waitForElementVisible('#step2', 4);
        $I->click('label[for="genderWoman"]');
        $I->fillField('#firstname', $this->first_name);
        $I->fillField('#lastname', $this->last_name);
        $I->click('weiter');

        // fill in birthdate
        $I->waitForElementVisible('#step3', 4);
        $I->click('#register-datepicker');
        $I->click('button[title="Vorheriges Jahr"]');
        $I->click('button[title="Vorheriges Jahr"]');
        $I->click('.b-calendar .b-calendar-grid-body .col[data-date] .btn');
        $I->click('weiter');

        $I->waitForElementVisible('#step4', 4);
        $I->fillField('input[class=vti__input]', $this->mobile_number);
        $I->click('weiter');

        // tick all the check boxes

        $I->waitForElementVisible('#step5', 4);
        $I->executeJS("$('#acceptGdpr').click()");
        $I->executeJS("$('#acceptLegal').click()");
        $I->executeJS("$('#subscribeNewsletter').click()");
        $I->click('Anmeldung absenden');

        // we are signed up!
        $I->waitForElementVisible('#step6', 4);
        $I->see('Du hast die Anmeldung bei foodsharing erfolgreich abgeschlossen.');

        $I->expectNumMails(1, 4);

        // now login as that user

        $I->amOnPage('/');

        $I->waitForElement('.testing-login-dropdown');
        $I->click('.testing-login-dropdown');
        $I->fillField('.testing-login-input-email', $this->email);
        $I->fillField('#testing-login-input-password > input', $this->password);
        $I->click('.testing-login-click-submit');
        $I->waitForActiveAPICalls();
        $I->waitForElementNotVisible('#pulse-success');
        $I->waitForPageBody();
        $I->waitForElement('.testing-intro-field');
        $I->see('Hallo', '.testing-intro-field');

        $I->seeInDatabase('fs_foodsaver', [
            'email' => $this->stripped_email,
            'name' => $this->first_name,
            'nachname' => $this->last_name,
            // 'geb_datum' => $this->birthdateUSFormat, // disabled because it's not possible to set a exact date in the frontend
            'newsletter' => 1,
            'handy' => $this->mobile_country_code . $this->mobile_number
        ]);
    }

    public function canRegisterNewUserWithoutNewsletter(AcceptanceTester $I): void
    {
        // create some unique values for our new user

        $I->wantTo('ensure I can register and will not receive newsletter by default');
        $I->amOnPage('/');

        // click signup, then press next on the first dialog

        $I->click('.testing-register-link');
        $I->click('Jetzt registrieren');
        $I->waitForElementVisible('#step1', 4);
        $I->fillField('#email', $this->email);
        $I->fillField('#password', $this->password);
        $I->fillField('#confirmPassword', $this->password);
        $I->click('weiter');

        // fill in basic details

        $I->waitForElementVisible('#step2', 4);
        $I->click('label[for="genderWoman"]');
        $I->fillField('#firstname', $this->first_name);
        $I->fillField('#lastname', $this->last_name);
        $I->click('weiter');

        // fill in birthdate
        $I->waitForElementVisible('#step3', 4);
        $I->click('#register-datepicker');
        $I->click('button[title="Vorheriges Jahr"]');
        $I->click('button[title="Vorheriges Jahr"]');
        $I->click('.b-calendar .b-calendar-grid-body .col[data-date] .btn');
        $I->click('weiter');

        $I->waitForElementVisible('#step4', 4);
        $I->fillField('input[class=vti__input]', $this->mobile_number);
        $I->click('weiter');

        // tick all the check boxes

        $I->waitForElementVisible('#step5', 4);
        $I->executeJS("$('#acceptGdpr').click()");
        $I->executeJS("$('#acceptLegal').click()");
        $I->click('Anmeldung absenden');

        // we are signed up!
        $I->waitForElementVisible('#step6', 4);
        $I->see('Du hast die Anmeldung bei foodsharing erfolgreich abgeschlossen.');

        $I->expectNumMails(1, 4);

        // now login as that user

        $I->amOnPage('/');

        $I->waitForElement('.testing-login-dropdown');
        $I->click('.testing-login-dropdown');
        $I->fillField('.testing-login-input-email', $this->email);
        $I->fillField('#testing-login-input-password > input', $this->password);
        $I->click('.testing-login-click-submit');
        $I->waitForActiveAPICalls();
        $I->waitForElementNotVisible('#pulse-success');
        $I->waitForPageBody();
        $I->waitForElement('.testing-intro-field');
        $I->see('Hallo', '.testing-intro-field');

        $I->seeInDatabase('fs_foodsaver', [
            'email' => $this->stripped_email,
            'name' => $this->first_name,
            'nachname' => $this->last_name,
            // 'geb_datum' => $this->birthdateUSFormat, // disabled because it's not possible to set a exact date in the frontend
            'newsletter' => 0,
            'handy' => $this->mobile_country_code . $this->mobile_number
        ]);
    }

    public function cannotRegisterNewUserWithBlacklistedEmailAddress(AcceptanceTester $I): void
    {
        $I->wantTo('get an error when trying to register with a blacklisted email address');

        $I->amOnPage('/');
        $I->click('.testing-register-link');
        $I->click('Jetzt registrieren');
        $I->waitForElementVisible('#step1', 4);
        $blacklistedEmailDomain = $I->grabFromDatabase('fs_email_blacklist', 'email', ['email like' => '%']);
        $I->fillField('#email', 'something@' . $blacklistedEmailDomain);
        $I->fillField('#password', $this->password);
        $I->fillField('#confirmPassword', $this->password);
        $I->click('weiter');

        $I->see('Die E-Mail-Adresse ist ungültig.');
    }
}

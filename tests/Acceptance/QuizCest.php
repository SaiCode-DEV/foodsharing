<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Example;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Tests\Support\AcceptanceTester;

class QuizCest
{
    // TODO
    private $foodsharer;
    private $foodsaver;
    private $storeManager;
    private $quizzes;

    public function _before(AcceptanceTester $I): void
    {
        $this->foodsharer = $I->createFoodsharer();
        $this->foodsaver = $I->createFoodsaver();

        $this->quizzes = [];
        foreach ([Role::FOODSAVER->value, Role::STORE_MANAGER->value] as $role) {
            $this->quizzes[$role] = $I->createQuiz($role);
        }
    }

    /**
     * @example["foodsharer", "Foodsaver:innen-Quiz", "Quiz ohne Zeitlimit", "Quiz ohne Zeitlimit"]
     * @example["foodsaver", "Betriebsverantwortlichen-Quiz", "Quiz mit Zeitlimit", "Quiz jetzt starten"]
     */
    public function canStartQuiz(AcceptanceTester $I, Example $example): void
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->settingsUrl());
        $I->see($example[1]);

        $quizRole = $this->{$example[0]}['rolle'] + 1;

        $id = $this->{$example[0]}['id'];
        $quizUrl = '/user/' . $id . '/settings?sub=rise_role&role=' . $quizRole;
        $I->amOnPage($quizUrl);

        $I->seeCurrentUrlEquals($quizUrl);
        $I->click($example[1]);
        $I->waitForActiveAPICalls();

        $I->waitForText('Jetzt das Quiz durchführen!');
        $I->click($example[2]);
        $I->waitForText('Los geht’s!');
        $I->click('Los geht’s!');

        $I->waitForText('Frage 1 von ');
        $I->waitForActiveAPICalls();

        $I->moveMouseOver('.answer-wrapper input');
        $I->clickWithLeftButton();
        $I->click('Weiter');
        $I->waitForActiveAPICalls();
        $I->click('Weiter');
        $I->waitForActiveAPICalls();

        $I->waitForText('Frage 2 von ');

        $I->reloadPage();
        $I->waitForText('Quiz jetzt weiter beantworten!');
        $I->click('Quiz jetzt weiter beantworten!');
        $I->waitForText('Los geht’s!');
        $I->click('Los geht’s!');

        $I->waitForText('Frage 2 von ');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Example;
use Codeception\Util\Locator;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\AcceptanceTester;

class ForumPostCest
{
    private $ambassador;
    private $foodsaver;
    private $unverifiedFoodsaver;
    private $testBezirk;
    private $bigTestBezirk;
    private $moderatedTestBezirk;
    private $thread_user_ambassador;
    private $thread_ambassador_user;

    public function _before(AcceptanceTester $I)
    {
        $this->testBezirk = $I->createRegion(fillMailbox: false);
        $this->bigTestBezirk = $I->createRegion(null, ['type' => UnitType::BIG_CITY], false);
        $this->moderatedTestBezirk = $I->createRegion(null, ['type' => UnitType::CITY, 'moderated' => true], false);
        $this->createUsers($I);
        $this->createPosts($I);
    }

    public function _after(AcceptanceTester $I)
    {
    }

    private function createUsers(AcceptanceTester $I)
    {
        $this->ambassador = $I->createAmbassador(null, ['bezirk_id' => $this->testBezirk['id']]);
        $this->foodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->testBezirk['id']]);
        $this->unverifiedFoodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->testBezirk['id'], 'verified' => false]);
        $I->addRegionAdmin($this->testBezirk['id'], $this->ambassador['id']);
        $I->addRegionAdmin($this->bigTestBezirk['id'], $this->ambassador['id']);
        $I->addRegionAdmin($this->moderatedTestBezirk['id'], $this->ambassador['id']);
        $I->addRegionMember($this->bigTestBezirk['id'], $this->foodsaver['id']);
        $I->addRegionMember($this->moderatedTestBezirk['id'], $this->foodsaver['id']);
    }

    private function createPosts(AcceptanceTester $I)
    {
        $this->thread_user_ambassador = $I->addForumThread($this->testBezirk['id'], $this->foodsaver['id'], false, ['time' => '2 hours ago']);
        $I->addForumThreadPost($this->thread_user_ambassador['id'], $this->ambassador['id'], ['time' => '1 hour 45 minutes ago']);
        $this->thread_ambassador_user = $I->addForumThread($this->testBezirk['id'], $this->ambassador['id'], false, ['time' => '1 hour ago']);
        $I->addForumThreadPost($this->thread_ambassador_user['id'], $this->foodsaver['id'], ['time' => '45 minutes ago']);
    }

    // tests

    public function ClickFollowUnfollow(AcceptanceTester $I)
    {
        $I->login($this->foodsaver['email']);
        $I->amOnPage($I->forumThreadUrl($this->thread_ambassador_user['id'], null));

        $button = '.subscribe-btn .btn-block';
        $dropdown = '.subscribe-btn .dropdown-toggle';
        $bellSwitch = '.dropdown-menu .bell-switch';
        $emailSwitch = '.dropdown-menu .email-switch';
        $isChecked = ' input:checked';
        $isNotChecked = ' input:not(:checked)';

        $I->waitForActiveAPICalls();
        $I->waitForElement($button);
        $I->see('Abonnieren', $button);
        $I->click($dropdown);
        $I->waitForElementVisible($bellSwitch);
        $I->seeElementInDOM($bellSwitch . $isNotChecked);
        $I->seeElementInDOM($emailSwitch . $isNotChecked);
        $I->click($dropdown);
        $I->waitForElementNotVisible($bellSwitch);
        $I->click($button);
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme_follower', [
            'foodsaver_id' => $this->foodsaver['id'],
            'theme_id' => $this->thread_ambassador_user['id'],
            'bell_notification' => 1,
            'infotype' => 0,
        ]);
        $I->see('Abonniert', $button);
        $I->click($dropdown);
        $I->waitForElementVisible($bellSwitch);
        $I->seeElementInDOM($bellSwitch . $isChecked);
        $I->seeElementInDOM($emailSwitch . $isNotChecked);
        $I->waitForElementVisible($bellSwitch);
        $I->click($bellSwitch . ' a');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme_follower', [
            'foodsaver_id' => $this->foodsaver['id'],
            'theme_id' => $this->thread_ambassador_user['id'],
            'bell_notification' => 0,
            'infotype' => 0,
        ]);
        $I->seeElementInDOM($bellSwitch . $isNotChecked);
        $I->click($bellSwitch . ' a');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme_follower', [
            'foodsaver_id' => $this->foodsaver['id'],
            'theme_id' => $this->thread_ambassador_user['id'],
            'bell_notification' => 1,
            'infotype' => 0,
        ]);
        $I->seeElementInDOM($bellSwitch . $isChecked);
        $I->seeElementInDOM($emailSwitch . $isNotChecked);

        $I->click($emailSwitch . ' a');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme_follower', [
            'foodsaver_id' => $this->foodsaver['id'],
            'theme_id' => $this->thread_ambassador_user['id'],
            'bell_notification' => 1,
            'infotype' => 1,
        ]);
        $I->seeElementInDOM($emailSwitch . $isChecked);
        $I->click($emailSwitch . ' a');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme_follower', [
            'foodsaver_id' => $this->foodsaver['id'],
            'theme_id' => $this->thread_ambassador_user['id'],
            'bell_notification' => 1,
            'infotype' => 0,
        ]);
        $I->seeElementInDOM($emailSwitch . $isNotChecked);
    }

    /**
     * @example["ambassador", "thread_ambassador_user"]
     * @example["ambassador", "thread_user_ambassador"]
     */
    public function CloseAndOpenThread(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->forumThreadUrl($this->{$example[1]}['id'], null));
        $I->seeInDatabase('fs_theme', [
            'id' => $this->{$example[1]}['id'],
            'status' => 0,
        ]);

        $overflowMenu = '.overflow-menu button';

        $I->waitForActiveAPICalls();
        $I->waitForElement($overflowMenu);

        $I->click($overflowMenu);
        $I->waitForText('Thema schließen');
        $I->click('Thema schließen');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme', [
            'id' => $this->{$example[1]}['id'],
            'status' => 1,
        ]);

        $I->waitForElement($overflowMenu);
        $I->click($overflowMenu);
        $I->waitForText('Thema öffnen');
        $I->click('Thema öffnen');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme', [
            'id' => $this->{$example[1]}['id'],
            'status' => 0,
        ]);
    }

    /**
     * @example["foodsaver", "thread_ambassador_user", false]
     * @example["foodsaver", "thread_user_ambassador", true]
     */
    public function CanNotCloseAndOpenThread(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->forumThreadUrl($this->{$example[1]}['id'], null));

        $overflowMenu = '.overflow-menu button';

        $I->waitForActiveAPICalls();
        if ($example[2]) {
            $I->click($overflowMenu);
            $I->dontSee('Thema schließen');
        } else {
            $I->dontSee($overflowMenu);
        }

        $I->updateInDatabase('fs_theme', ['status' => 1], ['id' => $this->{$example[1]}['id']]);
        $I->amOnPage($I->forumThreadUrl($this->{$example[1]}['id'], null));
        $I->waitForActiveAPICalls();
        if ($example[2]) {
            $I->click($overflowMenu);
            $I->dontSee('Thema öffnen');
        } else {
            $I->dontSee($overflowMenu);
        }
    }

    /**
     * @example["ambassador", "thread_ambassador_user"]
     * @example["ambassador", "thread_user_ambassador"]
     */
    public function PinAndUnpinThread(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->forumThreadUrl($this->{$example[1]}['id'], null));
        $I->seeInDatabase('fs_theme', [
            'id' => $this->{$example[1]}['id'],
            'sticky' => 0,
        ]);

        $overflowMenu = '.overflow-menu button';

        $I->waitForActiveAPICalls();
        $I->waitForElement($overflowMenu);

        $I->click($overflowMenu);
        $I->waitForText('Beitrag anheften');
        $I->click('Beitrag anheften');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme', [
            'id' => $this->{$example[1]}['id'],
            'sticky' => 1,
        ]);

        $I->waitForElement($overflowMenu);
        $I->click($overflowMenu);
        $I->waitForText('Nicht mehr anheften');
        $I->click('Nicht mehr anheften');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme', [
            'id' => $this->{$example[1]}['id'],
            'sticky' => 0,
        ]);
    }

    /**
     * @example["foodsaver", "thread_ambassador_user", false]
     * @example["foodsaver", "thread_user_ambassador", true]
     */
    public function CanNotPinAndUnpinThread(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->forumThreadUrl($this->{$example[1]}['id'], null));

        $overflowMenu = '.overflow-menu button';

        $I->waitForActiveAPICalls();
        if ($example[2]) {
            $I->click($overflowMenu);
            $I->dontSee('Beitrag anheften');
        } else {
            $I->dontSee($overflowMenu);
        }

        $I->updateInDatabase('fs_theme', ['sticky' => 1], ['id' => $this->{$example[1]}['id']]);
        $I->amOnPage($I->forumThreadUrl($this->{$example[1]}['id'], null));
        $I->waitForActiveAPICalls();
        if ($example[2]) {
            $I->click($overflowMenu);
            $I->dontSee('Nicht mehr anheften');
        } else {
            $I->dontSee($overflowMenu);
        }
    }

    /**
     * @example["ambassador", "thread_ambassador_user"]
     * @example["ambassador", "thread_user_ambassador"]
     * @example["foodsaver", "thread_user_ambassador"]
     */
    public function RenameThread(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->forumThreadUrl($this->{$example[1]}['id'], null));
        $I->updateInDatabase('fs_theme', ['name' => 'initial title'], ['id' => $this->{$example[1]}['id']]);

        $overflowMenu = '.overflow-menu button';
        $modalInput = '.modal-dialog input';

        $I->waitForActiveAPICalls();
        $I->waitForElement($overflowMenu);

        $I->click($overflowMenu);
        $I->waitForText('Titel bearbeiten');
        $I->see('Titel bearbeiten');
        $I->click('Titel bearbeiten');
        $I->waitForElementVisible($modalInput);
        $I->fillField($modalInput, 'new title');
        $I->click('Speichern');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_theme', [
            'id' => $this->{$example[1]}['id'],
            'name' => 'new title',
        ]);
    }

    private function _createThread(AcceptanceTester $I, $regionId, $title, $emailPossible, $sendEmail = false)
    {
        $I->amOnPage($I->forumUrl($regionId));
        $I->click('Neues Thema verfassen');
        $I->waitForPageBody();
        $I->fillField('#forum-create-thread-form-title', $title);
        $I->fillField('.md-input .md-text-area', 'TestThreadPost');
        $I->deleteAllMails();
        if (!$emailPossible) {
            $I->dontSee('Alle Forenmitglieder über die Erstellung dieses neuen Themas per E-Mail informieren');
        } elseif ($sendEmail) {
            $I->click('#send_mail_button');
        }
        $I->click('Anlegen');
        if ($sendEmail) {
            $I->waitForElementVisible('.modal-dialog');
            $I->click('Senden');
            $I->waitForElementNotVisible('.modal-dialog');
        }
        $I->waitForPageBody();
    }

    /**
     * @example["unverifiedFoodsaver", "testBezirk"]
     * @example["foodsaver", "bigTestBezirk"]
     * @example["foodsaver", "moderatedTestBezirk"]
     */
    public function newThreadWillBeModerated(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $title = 'TestThreadTitle';
        $I->deleteAllMails();
        $emailPossible = false;
        $this->_createThread($I, $this->{$example[1]}['id'], $title, $emailPossible);
        $I->amOnPage($I->forumUrl($this->{$example[1]}['id']));
        $I->dontSee($title);
        $I->expectNumMails(1, 5);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($title, $mail->text);
        $I->assertStringContainsString('tigt werden', $mail->subject);
    }

    /**
     * @example["foodsaver", "testBezirk"]
     */
    public function newThreadWillNotSendEmail(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $title = 'TestThreadTitleWithoutEmailToForumMembers';
        $I->deleteAllMails();
        $emailPossible = true;
        $sendEmail = false;
        $this->_createThread($I, $this->{$example[1]}['id'], $title, $emailPossible, $sendEmail);
        $I->amOnPage($I->forumUrl($this->{$example[1]}['id']));
        $I->waitForActiveAPICalls();
        $I->see($title);
        $I->expectNumMails(0);
    }

    /**
     * @example["foodsaver", "testBezirk"]
     */
    public function newThreadWillSendEmail(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $title = 'TestThreadTitleWithEmailToForumMembers';
        $I->deleteAllMails();
        $emailPossible = true;
        $sendEmail = true;
        $this->_createThread($I, $this->{$example[1]}['id'], $title, $emailPossible, $sendEmail);
        $I->amOnPage($I->forumUrl($this->{$example[1]}['id']));
        $I->waitForActiveAPICalls();
        $I->see($title);
        $I->wait(5);
        $numMails = count($I->getMails());
        $I->assertGreaterThan(0, $numMails);
    }

    /**
     * @example["ambassador", "thread_ambassador_user", true]
     */
    public function newThreadByAmbassadorWillNotBeModerated(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $title = 'TestAmbassadorThreadTitle';
        $this->_createThread($I, $this->testBezirk['id'], $title, true);
        $I->amOnPage($I->forumUrl($this->testBezirk['id']));
        $I->waitForActiveAPICalls();
        $I->see($title);
    }

    public function newThreadCanBeActivated(AcceptanceTester $I)
    {
        $I->login($this->foodsaver['email']);
        $I->deleteAllMails();
        $title = 'moderated thread to be activated';
        $this->_createThread($I, $this->moderatedTestBezirk['id'], $title, false);
        $I->wait(2); // wait a bit for the mails to arrive
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($title, $mail->text);
        $I->assertStringContainsString('tigt werden', $mail->subject);
        $I->assertRegExp('/http:\/\/.*region.*&amp;tid=[0-9]+/', $mail->html, 'mail should contain a link to thread');
        preg_match('/http:\/\/.*?\/(.*?)"/', (string)$mail->html, $matches);
        $link = html_entity_decode($matches[1]);
        $I->deleteAllMails();
        $admin = $I->haveFriend('admin');
        $admin->does(function (AcceptanceTester $I) use ($link, $title) {
            $I->login($this->ambassador['email']);
            $I->amOnPage($link);
            $I->waitForActiveAPICalls();
            $I->see($title);
            $I->click('Thema aktivieren');
            $I->waitForActiveAPICalls();
        });
        $I->amOnPage($I->forumUrl($this->moderatedTestBezirk['id']));
        $I->waitForActiveAPICalls();
        $I->see($title);
        /* There should have been notification mails - they are missing... */
        /* ...missing because thread activation currently doesn't send emails :( */
        /* Number of users in region, all (3) should get an email as soon as it is implemented */
        /* Well. We can check against 0 until it is implemented to not forget this test later on :) */
        $I->expectNumMails(0);
    }

    /**
     * @example["foodsaver", "moderatedTestBezirk"]
     */
    public function DeleteLastPostAndGetRedirectedToForum(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $title = 'TestThreadTitleForDeletion';
        $I->deleteAllMails();
        $this->_createThread($I, $this->{$example[1]}['id'], $title, false, false);
        $I->amOnPage($I->forumUrl($this->{$example[1]}['id']));
        $I->waitForActiveAPICalls();

        $I->expectNumMails(1, 5);
        $mail = $I->getMails()[0];
        preg_match('/http:\/\/.*?\/(.*?)"/', (string)$mail->html, $matches);
        $link = html_entity_decode($matches[1]);

        $admin = $I->haveFriend('admin');
        $admin->does(function (AcceptanceTester $I) use ($link, $title) {
            $I->login($this->ambassador['email']);
            $I->amOnPage($link);
            $I->waitForActiveAPICalls();
            $I->see($title);
            $I->click('Thema aktivieren');
            $I->waitForActiveAPICalls();
            $I->logMeOut();
        });

        $I->amOnPage($I->forumUrl($this->{$example[1]}['id']));
        $I->waitForActiveAPICalls();
        $I->canSee($title);
        $I->click('.forum_threads a');
        $I->waitForPageBody();
        $I->waitForActiveAPICalls();

        $regexForumUrl = preg_quote($I->forumUrl($this->{$example[1]}['id']));
        $regex = /* @lang PhpRegExp */ '~' . $regexForumUrl . '&tid=(\d+)~';
        $I->seeCurrentUrlMatches($regex);
        $I->waitForElement('a[title="Beitrag löschen"]');
        $I->click('a[title="Beitrag löschen"]');
        $I->waitForText('Beitrag löschen');
        $I->click(Locator::contains('.btn', 'Ja, ich bin mir sicher'));

        // Make the additional parameters optional in the pattern match
        $I->seeCurrentUrlMatches('~' . preg_quote($I->forumUrl($this->{$example[1]}['id'])) . '(?:[?&].*)?$~');

        $I->waitForText($title);
    }

    /**
     * Makes sure that created threads in moderated regions are not visible until they were activated. This includes
     * regions that are not marked as moderated but that are moderated because of their type (states, big cities, ...).
     *
     * @example["foodsaver", "bigTestBezirk"]
     * @example["foodsaver", "moderatedTestBezirk"]
     */
    public function canNotSeeInactiveThreads(AcceptanceTester $I, Example $example)
    {
        $I->login($this->{$example[0]}['email']);
        $title = 'TestThreadTitle';
        $this->_createThread($I, $this->{$example[1]}['id'], $title, true);
        $I->amOnPage($I->forumThreadUrl($this->{$example[1]}['id']));
        $I->waitForActiveAPICalls();
        $I->dontSee($title);
    }
}

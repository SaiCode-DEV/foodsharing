<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\BusinessCard\BusinessCardGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\Permissions\SettingsPermissions;
use Foodsharing\RestApi\Models\Settings\EmailChangeRequest;
use Foodsharing\RestApi\Models\Settings\PasswordChangeRequest;
use Foodsharing\Utility\EmailHelper;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Support\UnitTester;
use ValueError;

class SettingsTransactionsTest extends Unit
{
    protected ?MockObject $session = null;
    protected UnitTester $tester;
    private ?SettingsTransactions $transaction = null;

    /**
     * @throws Exception
     */
    public function _before(): void
    {
        $this->session = $this->createMock(Session::class);
        $this->transaction = new SettingsTransactions(
            $this->tester->get(FoodsaverGateway::class),
            $this->tester->get(LoginGateway::class),
            $this->tester->get(SettingsGateway::class),
            $this->tester->get(MailsGateway::class),
            $this->tester->get(EmailHelper::class),
            $this->tester->get(TranslatorInterface::class),
            $this->session,
            $this->tester->get(SettingsPermissions::class),
            $this->tester->get(FoodsaverTransactions::class),
            $this->tester->get(UnitGateway::class),
            $this->tester->get(RegionGateway::class),
            $this->tester->get(BellGateway::class),
            $this->tester->get(UrlGeneratorInterface::class),
            $this->tester->get(BusinessCardGateway::class),
        );
    }

    protected function _after()
    {
    }

    public function testOldSessionLocaleFormatConvertation(): void
    {
        $foodsaver = $this->tester->createFoodsaver();
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);
        // Reject content in new format, contains old formate ('local')
        $this->session->expects($this->any())->method('has')
            ->willReturnMap([['useroption_1', false], ['locale', true], ['locale_replaced', false]]);

        // Convert old SESSION Format (Has)
        $this->session->expects(self::atLeastOnce())->method('get')
            ->willReturnMap([
                    ['locale', 'de'],
                    ['useroption_1', 'de']
                ]
            );
        $this->session->expects($this->any())->method('set')->with($this->logicalOr('locale_replaced', 'useroption_1'), $this->logicalOr('de', true));

        $this->assertEquals('de', $this->transaction->getLocale());
    }

    public function testLoadOfOptionFromFsFoodsaverHasOptions(): void
    {
        $foodsaver = $this->tester->createFoodsaver(null);
        $this->tester->haveInDatabase('fs_foodsaver_has_options', [
            'foodsaver_id' => $foodsaver['id'],
            'option_type' => 2,
            'option_value' => '[1,2,3]']
        );
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);
        // Reject content in new format, reject old format ('activity-listings')
        $this->session->expects($this->any())->method('has')
            ->willReturnMap([['useroption_2', false], ['activity-listings', false]]);
        $this->session->expects(self::atLeastOnce())->method('set')->with('useroption_2', '[1,2,3]');
        $this->session->expects(self::atLeastOnce())->method('get')
            ->willReturnMap([
                    ['useroption_2', '[1,2,3]']
                ]
            );

        // Old from DB
        $this->assertEquals('[1,2,3]', $this->transaction->getOption(UserOptionType::ACTIVITY_LISTINGS));
    }

    public function testNoValueDefined(): void
    {
        $foodsaver = $this->tester->createFoodsaver(null);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);
        // Reject content in new format, reject old format ('activity-listings')
        $this->session->expects($this->any())->method('has')
            ->willReturnMap([['useroption_2', false], ['activity-listings', false]]);

        // Old from DB
        $this->assertEquals(null, $this->transaction->getOption(UserOptionType::ACTIVITY_LISTINGS));
    }

    public function testStartRequestEMailChange()
    {
        $foodsaver = $this->tester->createStoreCoordinator('NeedThePassword');

        $this->tester->cantSeeInDatabase('fs_mailchange', ['foodsaver_id' => $foodsaver['id']]);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);

        $changeRequest = new EmailChangeRequest();
        $changeRequest->email = 'Next@example.de';
        $changeRequest->password = 'NeedThePassword';

        // Action change of E-Mail
        $this->transaction->requestEmailChange($changeRequest);

        // Check creation of change token
        $this->tester->seeInDatabase('fs_mailchange', ['foodsaver_id' => $foodsaver['id'], 'newmail' => $changeRequest->email]);

        $this->tester->expectNumMails(2);
        $mails = $this->tester->getMails();

        // Check Notification on old Mail address
        $oldAddressMail = current(array_filter($mails, static fn ($item) => $item->headers->to == $foodsaver['email']));
        $this->tester->assertNotFalse($oldAddressMail, 'Mail not found');
        $this->tester->assertRegExp("/\/user\/current\/settings\/email\/verifyAbort\?token\=[0-9a-f]+/m", $oldAddressMail->html, 'Link is missing');

        // Check Notification with verification token in new E-Mail address
        $newAddressMail = current(array_filter($mails, static fn ($item) => $item->headers->to == strtolower($changeRequest->email)));
        $this->tester->assertNotFalse($newAddressMail, 'Mail not found');
        $this->tester->assertRegExp("/\/user\/current\/settings\/email\/verify\?token\=[0-9a-f]+/m", $newAddressMail->html, 'Link is missing');
    }

    public function testCancelRequestEMailChange()
    {
        $foodsaver = $this->tester->createStoreCoordinator('NeedThePassword');

        $this->tester->cantSeeInDatabase('fs_mailchange', ['foodsaver_id' => $foodsaver['id']]);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);

        $changeRequest = new EmailChangeRequest();
        $changeRequest->email = 'Next1@example.de';
        $changeRequest->password = 'NeedThePassword';

        // Action change of E-Mail
        $this->transaction->requestEmailChange($changeRequest);
        $this->tester->expectNumMails(2);
        $mails = $this->tester->getMails();

        // Find token for cancel in old e-mail
        $oldAddressMail = current(array_filter($mails, static fn ($item) => $item->headers->to == $foodsaver['email']));
        $this->tester->assertNotFalse($oldAddressMail, 'Mail not found');
        preg_match_all("/\/user\/current\/settings\/email\/verifyAbort\?token\=([0-9a-f]+)/m", $oldAddressMail->html, $tokens, PREG_SET_ORDER, 0);

        $this->assertNotNull($tokens);
        $token = $tokens[0][1];

        $this->transaction->abortEMailChange($token);
        $this->tester->dontSeeInDatabase('fs_mailchange', ['foodsaver_id' => $foodsaver['id']]);
        $this->tester->seeInDatabase('fs_foodsaver_change_history', ['fs_id' => $foodsaver['id'], 'object_name' => 'emailAbort', 'old_value' => $foodsaver['email'], 'new_value' => strtolower($changeRequest->email)]);

        // E-Mail??
    }

    public function testVerificationOfEMailChangeRequest()
    {
        $foodsaver = $this->tester->createStoreCoordinator('NeedThePassword');

        $this->tester->cantSeeInDatabase('fs_mailchange', ['foodsaver_id' => $foodsaver['id']]);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);

        $changeRequest = new EmailChangeRequest();
        $changeRequest->email = 'Next2@example.de';
        $changeRequest->password = 'NeedThePassword';

        // Action change of E-Mail
        $this->transaction->requestEmailChange($changeRequest);
        $this->tester->expectNumMails(2);
        $mails = $this->tester->getMails();

        // Find token for verify link in new e-mail
        $newAddressMail = current(array_filter($mails, static fn ($item) => $item->headers->to == strtolower($changeRequest->email)));
        $this->tester->assertNotFalse($newAddressMail, 'Mail not found');
        preg_match_all("/\/user\/current\/settings\/email\/verify\?token\=([0-9a-f]+)/m", $newAddressMail->html, $tokens, PREG_SET_ORDER, 0);

        $this->assertNotNull($tokens);
        $token = $tokens[0][1];

        $this->transaction->verifyAndCompleteEMailChange($token);
        $this->tester->dontSeeInDatabase('fs_mailchange', ['foodsaver_id' => $foodsaver['id']]);
        $this->tester->seeInDatabase('fs_foodsaver', ['id' => $foodsaver['id'], 'email' => strtolower($changeRequest->email)]);
        $this->tester->seeInDatabase('fs_foodsaver_change_history', ['fs_id' => $foodsaver['id'], 'object_name' => 'email', 'old_value' => $foodsaver['email'], 'new_value' => strtolower($changeRequest->email)]);

        // E-Mail??
    }

    public function testVerificationOfEMailChangeRequestFailsEMailInUse()
    {
        $foodsaver = $this->tester->createStoreCoordinator('NeedThePassword');

        $this->tester->cantSeeInDatabase('fs_mailchange', ['foodsaver_id' => $foodsaver['id']]);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);

        $changeRequest = new EmailChangeRequest();
        $changeRequest->email = 'Next2@example.de';
        $changeRequest->password = 'NeedThePassword';

        // Action change of E-Mail
        $this->transaction->requestEmailChange($changeRequest);
        $this->tester->expectNumMails(2);
        $mails = $this->tester->getMails();

        // Find token for verify link in new e-mail
        $newAddressMail = current(array_filter($mails, static fn ($item) => $item->headers->to == strtolower($changeRequest->email)));
        $this->tester->assertNotFalse($newAddressMail, 'Mail not found');
        preg_match_all("/\/user\/current\/settings\/email\/verify\?token\=([0-9a-f]+)/m", $newAddressMail->html, $tokens, PREG_SET_ORDER, 0);

        $this->assertNotNull($tokens);
        $token = $tokens[0][1];

        $foodsaver2 = $this->tester->createStoreCoordinator('NeedThePassword', ['email' => strtolower($changeRequest->email)]);

        try {
            $this->transaction->verifyAndCompleteEMailChange($token);
            $this->tester->assertFalse(true, 'Unexpected code executed');
        } catch (ValueError) {
        }
        $this->tester->dontSeeInDatabase('fs_mailchange', ['foodsaver_id' => $foodsaver['id']]);
        $this->tester->dontSeeInDatabase('fs_foodsaver', ['id' => $foodsaver['id'], 'email' => strtolower($changeRequest->email)]);
        $this->tester->dontSeeInDatabase('fs_foodsaver_change_history', ['fs_id' => $foodsaver['id'], 'object_name' => 'email', 'old_value' => $foodsaver['email'], 'new_value' => strtolower($changeRequest->email)]);

        // E-Mail??
    }

    public function testRequestPasswordChangeWithInvalidOldPassword(): void
    {
        $request = new PasswordChangeRequest();
        $request->oldPassword = '';
        $request->newPassword = '';

        $foodsaver = $this->tester->createFoodsaver('oldpassword');
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);

        $this->expectException(AccessDeniedHttpException::class);
        $this->transaction->requestPasswordChange($request);
        $this->tester->seeInDatabase('fs_foodsaver', ['id' => $foodsaver['id'], 'password' => $foodsaver['password']]);
    }

    public function testRequestPasswordChangeWithShortPassword(): void
    {
        $request = new PasswordChangeRequest();
        $request->oldPassword = 'oldpassword';
        $request->newPassword = 'abc';

        $foodsaver = $this->tester->createFoodsaver($request->oldPassword);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);

        $this->expectException(BadRequestHttpException::class);
        $this->transaction->requestPasswordChange($request);
        $this->tester->seeInDatabase('fs_foodsaver', ['id' => $foodsaver['id'], 'password' => $foodsaver['password']]);
    }

    public function testRequestPasswordChangeWithSimplePassword(): void
    {
        $request = new PasswordChangeRequest();
        $request->oldPassword = 'oldpassword';
        $request->newPassword = 'abcabcabc';

        $foodsaver = $this->tester->createFoodsaver($request->oldPassword);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);

        $this->expectException(BadRequestHttpException::class);
        $this->transaction->requestPasswordChange($request);
        $this->tester->seeInDatabase('fs_foodsaver', ['id' => $foodsaver['id'], 'password' => $foodsaver['password']]);
    }

    public function testRequestPasswordChangeValid(): void
    {
        $request = new PasswordChangeRequest();
        $request->oldPassword = 'oldpassword';
        $request->newPassword = 'abcdefghijABC123';

        $foodsaver = $this->tester->createFoodsaver($request->oldPassword);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);
        $this->tester->assertTrue(password_verify(
            $request->oldPassword,
            (string)$this->tester->grabFromDatabase('fs_foodsaver', 'password', ['id' => $foodsaver['id']])
        ));

        $this->transaction->requestPasswordChange($request);
        $this->tester->assertTrue(password_verify(
            $request->newPassword,
            (string)$this->tester->grabFromDatabase('fs_foodsaver', 'password', ['id' => $foodsaver['id']])
        ));
    }
}

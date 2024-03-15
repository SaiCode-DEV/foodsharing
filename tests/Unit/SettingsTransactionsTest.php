<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\Utility\EmailHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Support\UnitTester;

class SettingsTransactionsTest extends Unit
{
    protected ?MockObject $session = null;
    protected UnitTester $tester;
    private ?SettingsTransactions $transaction = null;
    private array $foodsaver;

    public function _before()
    {
        $this->session = $this->createMock(Session::class);
        $this->transaction = new SettingsTransactions(
            $this->tester->get(FoodsaverGateway::class),
            $this->tester->get(LoginGateway::class),
            $this->tester->get(SettingsGateway::class),
            $this->tester->get(MailsGateway::class),
            $this->tester->get(EmailHelper::class),
            $this->tester->get(TranslatorInterface::class),
            $this->session);
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
        $foodsaver = $this->tester->createFoodsaver(null, ['option' => '']);
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
        $foodsaver = $this->tester->createFoodsaver(null, ['option' => '']);
        $this->session->expects($this->any())->method('id')->willReturn($foodsaver['id']);
        // Reject content in new format, reject old format ('activity-listings')
        $this->session->expects($this->any())->method('has')
            ->willReturnMap([['useroption_2', false], ['activity-listings', false]]);

        // Old from DB
        $this->assertEquals(null, $this->transaction->getOption(UserOptionType::ACTIVITY_LISTINGS));
    }
}

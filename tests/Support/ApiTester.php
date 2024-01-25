<?php

declare(strict_types=1);

namespace Tests\Support;

use Codeception\Actor;
use Codeception\Lib\Friend;
use Codeception\Scenario;
use Tests\Support\_generated\ApiTesterActions;

/**
 * Inherited Methods.
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method Friend haveFriend($name, $actorClass = NULL)
 *
 * @SuppressWarnings(PHPMD)
 */
class ApiTester extends Actor
{
    use ApiTesterActions;

    public function __construct(Scenario $scenario)
    {
        parent::__construct($scenario);
        $this->haveHttpHeader('X-CSRF-Token', CSRF_TEST_TOKEN);
    }

    /**
     * Checks the content type is html, and the content contains html.
     */
    public function seeHtml(): void
    {
        $I = $this;
        $I->seeHttpHeader('Content-Type', 'text/html; charset=UTF-8');
        $I->seeResponseIsHtml();
    }
}

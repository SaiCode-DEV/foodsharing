<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Tests\Support\UnitTester;

class ContentGatewayTest extends Unit
{
    protected UnitTester $tester;
    private ContentGateway $gateway;

    public function _before()
    {
        $this->gateway = $this->tester->get(ContentGateway::class);
    }

    public function testGetContent(): void
    {
        $content = $this->gateway->getContent(ContentId::QUIZ_REMARK_PAGE_33);
        $this->assertNotNull($content);
        $this->assertEquals('Wichtiger Hinweis:', $content->title);
        $this->assertStringContainsString('Lebensmittelverschwendung', $content->body);
    }
}

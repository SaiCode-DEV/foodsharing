<?php

declare(strict_types=1);

namespace Tests\Unit;

use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\Modules\Index\IndexController;
use PHPUnit\Framework\TestCase;

/**
 * Start-page per-country content selection (#1592).
 */
class IndexControllerStartpageHostTest extends TestCase
{
    /**
     * @dataProvider hostProvider
     */
    public function testHostSelectsExpectedStartpageBlocks(string $host, array $expected): void
    {
        $this->assertSame($expected, IndexController::startpageContentIdsForHost($host));
    }

    public static function hostProvider(): array
    {
        $ch = [ContentId::STARTPAGE_BLOCK1_CH, ContentId::STARTPAGE_BLOCK2_CH, ContentId::STARTPAGE_BLOCK3_CH];

        return [
            'austria' => ['foodsharing.at',
                [ContentId::STARTPAGE_BLOCK1_AT, ContentId::STARTPAGE_BLOCK2_AT, ContentId::STARTPAGE_BLOCK3_AT]],
            // #1592: the Swiss site is served on foodsharing.network — it must
            // select the Swiss blocks rather than falling back to DE.
            'switzerland-network' => ['foodsharing.network', $ch],
            'switzerland-ch-redirect' => ['foodsharingschweiz.ch', $ch],
            'beta-de' => ['beta.foodsharing.de',
                [ContentId::STARTPAGE_BLOCK1_BETA, ContentId::STARTPAGE_BLOCK2_BETA, ContentId::STARTPAGE_BLOCK3_BETA]],
            'germany' => ['foodsharing.de',
                [ContentId::STARTPAGE_BLOCK1_DE, ContentId::STARTPAGE_BLOCK2_DE, ContentId::STARTPAGE_BLOCK3_DE]],
        ];
    }
}

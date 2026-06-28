<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Login;

use Foodsharing\Modules\Login\EmailBlocklistGateway;
use Foodsharing\Modules\Login\EmailBlocklistTransactions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class EmailBlocklistTransactionsTest extends TestCase
{
    private EmailBlocklistTransactions $transactions;
    private EmailBlocklistGateway&MockObject $gatewayMock;
    private CacheInterface&MockObject $cacheMock;

    protected function setUp(): void
    {
        $this->gatewayMock = $this->createMock(EmailBlocklistGateway::class);
        $this->cacheMock = $this->createMock(CacheInterface::class);
        $this->transactions = new EmailBlocklistTransactions($this->gatewayMock, $this->cacheMock);
    }

    /**
     * Test exact email match (no wildcards, no domain-only pattern).
     */
    public function testExactEmailMatch(): void
    {
        $patterns = ['user@example.com'];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('user@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('USER@EXAMPLE.COM')); // Case-insensitive
        $this->assertFalse($this->transactions->isEmailBlocked('other@example.com'));
    }

    /**
     * Test legacy domain-only pattern (no @ in pattern).
     * Pattern "example.com" should block any user@example.com.
     */
    public function testLegacyDomainOnlyPattern(): void
    {
        $patterns = ['example.com'];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('user@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('admin@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('USER@EXAMPLE.COM'));
        $this->assertFalse($this->transactions->isEmailBlocked('user@other.com'));
    }

    /**
     * Test wildcard at beginning: *@example.com blocks any user at that domain.
     */
    public function testWildcardAtBeginning(): void
    {
        $patterns = ['*@example.com'];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('user@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('admin@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('test.user@example.com'));
        $this->assertFalse($this->transactions->isEmailBlocked('user@other.com'));
    }

    /**
     * Test wildcard at end: user@* blocks user at any domain.
     */
    public function testWildcardAtEnd(): void
    {
        $patterns = ['user@*'];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('user@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('user@mail.example.com'));
        $this->assertFalse($this->transactions->isEmailBlocked('admin@example.com'));
    }

    /**
     * Test wildcard in middle: user@*.example.com blocks user@mail.example.com, user@smtp.example.com, etc.
     */
    public function testWildcardInMiddle(): void
    {
        $patterns = ['user@*.example.com'];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('user@mail.example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('user@smtp.example.com'));
        $this->assertFalse($this->transactions->isEmailBlocked('user@example.com')); // No subdomain
        $this->assertFalse($this->transactions->isEmailBlocked('admin@mail.example.com'));
    }

    /**
     * Test plus-alias normalization: user+tag@example.com matches user@example.com pattern.
     */
    public function testPlusAliasNormalization(): void
    {
        $patterns = ['user@example.com'];
        $this->setupCacheMock($patterns);

        // user+tag@example.com should normalize to user@example.com and match
        $this->assertTrue($this->transactions->isEmailBlocked('user+tag@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('user+test@example.com'));
        $this->assertFalse($this->transactions->isEmailBlocked('other+tag@example.com'));
    }

    /**
     * Test plus-alias with wildcard pattern: *@example.com matches user+tag@example.com.
     */
    public function testPlusAliasWithWildcard(): void
    {
        $patterns = ['*@example.com'];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('user+tag@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('user+test+nested@example.com'));
    }

    /**
     * Test whitespace trimming.
     */
    public function testWhitespaceTrimming(): void
    {
        $patterns = ['  user@example.com  '];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('  user@example.com  '));
        $this->assertTrue($this->transactions->isEmailBlocked('user@example.com'));
    }

    /**
     * Test multiple patterns (one should match).
     */
    public function testMultiplePatterns(): void
    {
        $patterns = [
            'admin@example.com',
            '*@disposable.com',
            'user@mail.*',
        ];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('admin@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('spammer@disposable.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('user@mail.google.com'));
        $this->assertFalse($this->transactions->isEmailBlocked('legit@example.com'));
    }

    /**
     * Test that an email matching a pattern returns true (simple cache flow).
     */
    public function testBlockedEmailReturnsTrueFromCache(): void
    {
        $patterns = ['*@tempmail.com'];
        $this->setupCacheMock($patterns);

        $result = $this->transactions->isEmailBlocked('test@tempmail.com');
        $this->assertTrue($result);
    }

    /**
     * Test that a non-matching email returns false.
     */
    public function testNonBlockedEmailReturnsFalse(): void
    {
        $patterns = ['*@tempmail.com'];
        $this->setupCacheMock($patterns);

        $result = $this->transactions->isEmailBlocked('test@gmail.com');
        $this->assertFalse($result);
    }

    /**
     * Test cache invalidation on entry creation.
     */
    public function testCacheInvalidationOnCreate(): void
    {
        $this->gatewayMock
            ->expects($this->once())
            ->method('createEntry')
            ->with('*@example.com', 'Spam domain', true, 1)
            ->willReturn(42);

        $this->cacheMock
            ->expects($this->once())
            ->method('delete')
            ->with('email_blocklist_patterns');

        $id = $this->transactions->createEntry('*@example.com', 'Spam domain', true, 1);
        $this->assertSame(42, $id);
    }

    /**
     * Test cache invalidation on entry update.
     */
    public function testCacheInvalidationOnUpdate(): void
    {
        $this->gatewayMock
            ->expects($this->once())
            ->method('updateEntry')
            ->with(1, ['reason' => 'Updated reason']);

        $this->cacheMock
            ->expects($this->once())
            ->method('delete')
            ->with('email_blocklist_patterns');

        $this->transactions->updateEntry(1, ['reason' => 'Updated reason']);
    }

    /**
     * Test cache invalidation on entry deletion.
     */
    public function testCacheInvalidationOnDelete(): void
    {
        $this->gatewayMock
            ->expects($this->once())
            ->method('deleteEntry')
            ->with(1);

        $this->cacheMock
            ->expects($this->once())
            ->method('delete')
            ->with('email_blocklist_patterns');

        $this->transactions->deleteEntry(1);
    }

    /**
     * Test cache invalidation on account deletion entry creation.
     */
    public function testCacheInvalidationOnAccountDeletionEntry(): void
    {
        $this->gatewayMock
            ->expects($this->once())
            ->method('createEntryFromAccountDeletion')
            ->with('user@example.com', 'Account deleted', 1)
            ->willReturn(99);

        $this->cacheMock
            ->expects($this->once())
            ->method('delete')
            ->with('email_blocklist_patterns');

        $id = $this->transactions->createEntryFromAccountDeletion('user@example.com', 'Account deleted', 1);
        $this->assertSame(99, $id);
    }

    /**
     * Test no cache invalidation when account deletion returns null.
     */
    public function testNoCacheInvalidationWhenAccountDeletionReturnsNull(): void
    {
        $this->gatewayMock
            ->expects($this->once())
            ->method('createEntryFromAccountDeletion')
            ->willReturn(null);

        $this->cacheMock
            ->expects($this->never())
            ->method('delete');

        $result = $this->transactions->createEntryFromAccountDeletion('user@example.com', null, 1);
        $this->assertNull($result);
    }

    /**
     * Test edge case: empty pattern list (nothing blocked).
     */
    public function testEmptyPatternList(): void
    {
        $patterns = [];
        $this->setupCacheMock($patterns);

        $this->assertFalse($this->transactions->isEmailBlocked('user@example.com'));
    }

    /**
     * Test edge case: pattern with multiple @ signs (should not match unless explicitly provided).
     */
    public function testPatternWithMultipleAtSigns(): void
    {
        $patterns = ['user@@example.com'];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('user@@example.com'));
        $this->assertFalse($this->transactions->isEmailBlocked('user@example.com'));
    }

    /**
     * Test case-insensitivity with mixed case patterns.
     */
    public function testCaseInsensitivity(): void
    {
        $patterns = ['User@Example.COM'];
        $this->setupCacheMock($patterns);

        $this->assertTrue($this->transactions->isEmailBlocked('user@example.com'));
        $this->assertTrue($this->transactions->isEmailBlocked('USER@EXAMPLE.COM'));
        $this->assertTrue($this->transactions->isEmailBlocked('UsEr@ExAmPlE.cOm'));
    }

    /**
     * Helper to set up cache mock to return patterns.
     *
     * @param array<string> $patterns
     */
    private function setupCacheMock(array $patterns): void
    {
        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                if ($key === 'email_blocklist_patterns') {
                    $itemMock = $this->createMock(ItemInterface::class);

                    return $callback($itemMock);
                }
            });

        $this->gatewayMock
            ->expects($this->once())
            ->method('getActivePatterns')
            ->willReturn($patterns);
    }
}

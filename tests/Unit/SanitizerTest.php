<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Utility\Sanitizer;
use Tests\Support\UnitTester;

class SanitizerTest extends Unit
{
    protected UnitTester $tester;
    private Sanitizer $sanitizer;

    final public function _before(): void
    {
        $this->sanitizer = $this->tester->get(Sanitizer::class);
    }

    // tests
    final public function testPlainToHtmlEncodesTags(): void
    {
        $in = 'Hi<there>, you <b>keep this</b>?';
        $out = $this->sanitizer->plainToHtml($in);
        $this->assertEquals(
            'Hi&lt;there&gt;, you &lt;b&gt;keep this&lt;/b&gt;?',
            $out
        );
    }

    final public function testPurifyHtmlStripsScriptTag(): void
    {
        $in = '<p>This should stay</p><script type="text/javascript">alert()</script><p>And this is last</p>';
        $out = $this->sanitizer->purifyHtml($in);
        $this->assertEquals(
            '<p>This should stay</p><p>And this is last</p>',
            $out
        );
    }

    final public function testMarkdownToHtmlEncodesTags(): void
    {
        $in = 'Hi<there>, you <b>keep this</b>?';
        $out = $this->sanitizer->markdownToHtml($in);
        $this->assertEquals(
            '<p>Hi&lt;there&gt;, you &lt;b&gt;keep this&lt;/b&gt;?</p>',
            $out
        );
    }

    final public function testMarkdownToHtmlHandlesNewline(): void
    {
        $in = "Hi\nthere";
        $out = $this->sanitizer->markdownToHtml($in);
        $this->assertStringContainsString(
            'Hi<br />',
            $out
        );
        /* We do not want to specify if it keeps newline or not, but we want to have a break in the output. */
        $this->assertStringContainsString(
            'there',
            $out
        );
    }

    final public function testHtmlToPlainConvertsNewline(): void
    {
        $in = 'Hi<br />there';
        $out = $this->sanitizer->htmlToPlain($in);
        $this->assertEquals(
            "Hi\nthere",
            $out
        );
    }

    /* #2870: html2text writes "text [url]" and mail clients include the bracket - and the
       punctuation behind it - in the link they detect. */
    final public function testHtmlToPlainSeparatesLinkFromUrlByWhitespace(): void
    {
        $in = '<p>Von <a href="https://foodsharing.de/profile/1">Andrea</a>:</p>';
        $out = $this->sanitizer->htmlToPlain($in);
        $this->assertEquals(
            'Von Andrea https://foodsharing.de/profile/1:',
            trim($out)
        );
    }

    final public function testHtmlToPlainWritesLinkWithoutMarkupCharacters(): void
    {
        $in = '<a href="https://foodsharing.de/store/7">Betrieb</a>';
        $out = trim($this->sanitizer->htmlToPlain($in));
        /* Angle brackets would be swallowed by the strip_tags() inside the library. */
        $this->assertStringNotContainsString('[', $out);
        $this->assertStringNotContainsString('<', $out);
        $this->assertStringNotContainsString('>', $out);
    }

    final public function testHtmlToPlainPrintsSelfLabelledLinkOnlyOnce(): void
    {
        $in = '<a href="https://foodsharing.de/reset">https://foodsharing.de/reset </a>';
        $out = $this->sanitizer->htmlToPlain($in);
        $this->assertEquals(
            'https://foodsharing.de/reset',
            trim($out)
        );
    }

    final public function testHtmlToPlainKeepsMailtoLabel(): void
    {
        $in = '<a href="mailto:info@foodsharing.de">Support</a>';
        $out = $this->sanitizer->htmlToPlain($in);
        $this->assertEquals(
            'Support',
            trim($out)
        );
    }

    final public function testMarkdownRendersSimpleList(): void
    {
        $in = "* Hi\n* there";
        $out = $this->sanitizer->markdownToHtml($in);
        $this->assertStringContainsString(
            '<li>Hi</li>',
            $out
        );
    }

    final public function testTruncateWord(): void
    {
        $in = 'I am a too long text';
        $this->assertEquals(' ...', $this->sanitizer->tt($in, 4));
        $this->assertEquals('I ...', $this->sanitizer->tt($in, 5));
        $this->assertEquals('I am ...', $this->sanitizer->tt($in, 8));
        $this->assertEquals('I am ...', $this->sanitizer->tt($in, 9));
        $this->assertEquals('I am a ...', $this->sanitizer->tt($in, 10));
    }

    final public function testTruncateUtf8Word(): void
    {
        $in = 'Hi 😂 you!';
        $this->assertEquals('Hi ...', $this->sanitizer->tt($in, 6));
        $this->assertEquals('Hi ...', $this->sanitizer->tt($in, 7));
        $this->assertEquals('Hi 😂 ...', $this->sanitizer->tt($in, 8));
        $this->assertEquals($in, $this->sanitizer->tt($in, 9));
    }
}

import assert from 'assert'
import { sanitizeHtml } from './sanitize-html'

// Regression guard for the single hardened client-side sanitizer. These cases
// must keep passing across every future dompurify bump -- they encode the
// security contract, not the library's internals.
describe('sanitizeHtml', () => {
  it('strips <script> elements', () => {
    const out = sanitizeHtml('<p>ok</p><script>alert(1)</script>')
    assert.ok(!/<script/i.test(out), out)
    assert.ok(out.includes('ok'), out)
  })

  it('strips inline event handlers', () => {
    const out = sanitizeHtml('<img src=x onerror=alert(1)>')
    assert.ok(!/onerror/i.test(out), out)
  })

  it('drops javascript: URLs', () => {
    const out = sanitizeHtml('<a href="javascript:alert(1)">x</a>')
    assert.ok(!/javascript:/i.test(out), out)
  })

  // CVE-2026-0540: rawtext elements (noscript/xmp/noembed/noframes/iframe) were
  // missing from the SAFE_FOR_XML regex in dompurify 3.1.3-3.3.1, allowing an
  // attribute-sanitization breakout. dompurify 3.2.7 (our previous pin) was
  // affected; this asserts no active content survives such payloads.
  it('neutralizes rawtext-breakout payloads (CVE-2026-0540 class)', () => {
    const payloads = [
      '<noscript><p title="</noscript><img src=x onerror=alert(1)>">',
      '<xmp><img src=x onerror=alert(1)></xmp>',
      '<noembed><img src=x onerror=alert(1)></noembed>',
      '<noframes><img src=x onerror=alert(1)></noframes>',
    ]
    for (const p of payloads) {
      const out = sanitizeHtml(p)
      assert.ok(!/onerror/i.test(out), `onerror survived: ${p} -> ${out}`)
      assert.ok(!/<script/i.test(out), `script survived: ${p} -> ${out}`)
    }
  })

  it('hardens external links with target and rel', () => {
    const out = sanitizeHtml('<a href="https://evil.example/x">click</a>')
    assert.ok(/target="_blank"/.test(out), out)
    assert.ok(/rel="noopener noreferrer nofollow"/.test(out), out)
  })

  it('leaves internal links untouched (no forced new tab)', () => {
    const out = sanitizeHtml('<a href="/profile/123">user</a>')
    assert.ok(!/target="_blank"/.test(out), out)
    assert.ok(out.includes('/profile/123'), out)
  })

  it('keeps benign formatting markup', () => {
    const out = sanitizeHtml('<p><strong>bold</strong> and <em>italic</em></p>')
    assert.ok(out.includes('<strong>bold</strong>'), out)
    assert.ok(out.includes('<em>italic</em>'), out)
  })

  it('handles null/undefined input without throwing', () => {
    assert.strictEqual(sanitizeHtml(null), '')
    assert.strictEqual(sanitizeHtml(undefined), '')
  })
})

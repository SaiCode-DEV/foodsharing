import assert from 'assert'
import t, { escapeLinkedTokens, i18nInstance } from '@/helper/i18n'

// Regression test for #2718: a literal '@' in a translation (e.g. the gender-neutral
// Spanish "¿Estas segur@?") must not throw "Invalid linked format" in the vue-i18n
// message compiler — it has to render as a literal '@'.
describe('i18n literal @ handling (#2718)', () => {
  it('escapeLinkedTokens escapes a literal @ to the vue-i18n literal', () => {
    assert.strictEqual(escapeLinkedTokens('¿Estas segur@?'), "¿Estas segur{'@'}?")
    assert.strictEqual(escapeLinkedTokens('info@foodsharing.de'), "info{'@'}foodsharing.de")
  })

  it('leaves strings without @ and non-string values untouched', () => {
    assert.strictEqual(escapeLinkedTokens('Bist du sicher?'), 'Bist du sicher?')
    assert.deepStrictEqual(escapeLinkedTokens({ a: 'x@y', b: 'z' }), { a: "x{'@'}y", b: 'z' })
  })

  it('renders an escaped message containing a literal @ instead of throwing', () => {
    // Fresh key merged into 'de' (the active/fallback locale) so it goes through the
    // real compiler without hitting an already-compiled cache entry.
    const messages = escapeLinkedTokens({ test_2718_at_sign: '¿Estas segur@?' })
    i18nInstance.global.mergeLocaleMessage('de', messages)
    assert.strictEqual(t('test_2718_at_sign'), '¿Estas segur@?')
  })
})

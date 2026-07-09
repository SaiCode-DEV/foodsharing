import { mount } from '@vue/test-utils'
import assert from 'assert'
import Markdown from './Markdown.vue'

// Integration guard: Markdown.vue renders markdown-it output (configured with
// `html: true`) into v-html. This asserts the end-to-end security contract --
// the rendered HTML contains no active content -- independent of the mechanism
// (the 'zero' preset currently escapes raw HTML; the shared sanitizer is
// defense-in-depth should that ever change). It also guards that benign
// markdown still renders, so a future over-eager sanitizer change is caught.
describe('Markdown.vue', () => {
  async function renderDom (source) {
    const wrapper = mount(Markdown, { propsData: { source } })
    // mounted() -> render(); htmlContent assignment is synchronous, DOM flush
    // needs a tick.
    await wrapper.vm.$nextTick()
    // Parse via a detached <template>: its content is inert (no scripts run,
    // no resources load) so nothing can execute during the assertions.
    const tpl = document.createElement('template')
    tpl.innerHTML = wrapper.vm.htmlContent
    return tpl.content
  }

  function hasEventHandlerAttr (doc) {
    return [...doc.querySelectorAll('*')].some(el =>
      [...el.attributes].some(a => a.name.toLowerCase().startsWith('on')))
  }

  function hasDangerousUrl (doc) {
    return [...doc.querySelectorAll('[href],[src]')].some(el => {
      const v = (el.getAttribute('href') || el.getAttribute('src') || '').trim()
      return /^javascript:/i.test(v)
    })
  }

  it('produces no <script> element from the source', async () => {
    const doc = await renderDom('hello <script>alert(1)</script> world')
    assert.strictEqual(doc.querySelectorAll('script').length, 0)
  })

  it('produces no inline event handler attributes from the source', async () => {
    const doc = await renderDom('<img src=x onerror=alert(1)>')
    assert.ok(!hasEventHandlerAttr(doc))
  })

  it('produces no javascript: URLs from the source', async () => {
    const doc = await renderDom('<a href="javascript:alert(1)">x</a>')
    assert.ok(!hasDangerousUrl(doc))
  })

  it('still renders benign markdown formatting', async () => {
    const doc = await renderDom('**bold** and *italic*')
    assert.ok(doc.querySelector('strong'))
    assert.strictEqual(doc.querySelector('strong').textContent, 'bold')
    assert.ok(doc.querySelector('em'))
  })
})

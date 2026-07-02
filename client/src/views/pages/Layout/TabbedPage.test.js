import '@/vue'
import { mount } from '@vue/test-utils'
import assert from 'assert'
import TabbedPage from './TabbedPage.vue'
import ResponsiveTab from '@/components/TabView/ResponsiveTab.vue'

const createContainer = () => {
  const el = document.createElement('div')
  document.body.appendChild(el)
  return el
}

// Force the mobile layout so the test does not depend on BasePage/desktop deps.
Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: 800 })

// Two tabs share the exact same title and a third has an empty title. This mirrors
// the real bug where an untranslated tab title is an empty string in some locales
// (fr/es/it), so several tab titles collide. See #2755.
const Host = {
  render (h) {
    return h(TabbedPage, [
      h(ResponsiveTab, { props: { title: 'Same' } }, [h('span', { class: 'content-a' }, 'AAA')]),
      h(ResponsiveTab, { props: { title: 'Same' } }, [h('span', { class: 'content-b' }, 'BBB')]),
      h(ResponsiveTab, { props: { title: '' } }, [h('span', { class: 'content-c' }, 'CCC')]),
    ])
  },
}

describe('TabbedPage', () => {
  it('does not crash when tab titles collide or are empty', () => {
    const wrapper = mount(Host, { attachTo: createContainer() })
    // Regression for #2755: duplicate/empty titles used to throw
    // "Duplicate tab title detected" and blank the whole page.
    const items = wrapper.findAll('.list-group-item')
    assert.strictEqual(items.length, 3)
  })

  it('resolves tab content by position, not by title', async () => {
    const wrapper = mount(Host, { attachTo: createContainer() })

    // Select the second same-titled tab: content must be its own, not the first one's.
    await wrapper.findAll('.list-group-item').at(1).trigger('click')
    assert(wrapper.find('.content-b').exists(), 'second tab shows its own content')
    assert(!wrapper.find('.content-a').exists(), 'not the first tab content')
  })

  it('keeps the empty-titled tab reachable', async () => {
    const wrapper = mount(Host, { attachTo: createContainer() })

    await wrapper.findAll('.list-group-item').at(2).trigger('click')
    assert(wrapper.find('.content-c').exists(), 'empty-titled tab shows its own content')
  })
})

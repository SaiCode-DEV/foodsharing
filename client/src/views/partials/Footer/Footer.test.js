/* global describe, it, beforeEach */
import { mount, createLocalVue } from '@vue/test-utils'
import '@/vue'
import { setActivePinia, createPinia } from 'pinia'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'

const assert = require('assert')

const localVue = createLocalVue()
localVue.prototype.$t = (key, variables = {}) => i18n(key, variables)
localVue.prototype.$url = url

describe('Footer', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('shows the social media links on the Austrian (.at) site (#1596)', async () => {
    const Footer = require('./Footer.vue').default
    const wrapper = mount(Footer, { localVue })

    // Simulate being on foodsharing.at. Before the fix the social block was
    // hidden via v-if="!isDotAt"; it must now be shown for .at too.
    await wrapper.setData({ isDotAt: true })

    const socials = wrapper.findAll('.social_icons')
    assert.ok(socials.length > 0, 'social media links should be shown on .at')
    // The .at-specific accounts should be linked.
    const hrefs = socials.wrappers.map(w => w.attributes('href'))
    assert.ok(hrefs.some(h => h && h.includes('foodsharing.at')), 'Instagram .at link present')
    assert.ok(hrefs.some(h => h && h.includes('oesterreichfoodsharing')), 'Facebook .at link present')
  })
})

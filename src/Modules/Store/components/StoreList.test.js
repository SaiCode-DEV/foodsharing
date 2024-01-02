import sinon from 'sinon'
import { mount, createLocalVue } from '@vue/test-utils'
import { resetModules } from '>/utils'
import '@/vue'
import { setActivePinia, createPinia } from 'pinia'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'
import { isValidPhoneNumber } from '@/helper/phone-numbers'
import dateFormatter from '@/helper/date-formatter'

const assert = require('assert')

const localVue = createLocalVue()

localVue.$i18n = (key, variables = {}) => {
  return i18n(key, variables)
}
localVue.prototype.$url = url
localVue.prototype.$isValidPhoneNumber = isValidPhoneNumber
localVue.prototype.$dateFormatter = dateFormatter

function createMockStore () {
  return {
    added: '1983-04-10',
    address: 'Tanja-Oswald-Ring 08c 281',
    id: 15906,
    name: 'betrieb_Bader Hammer KG',
    region: 'Göttingen',
    status: 3,
  }
}

describe('StoreRegionList', () => {
  const sandbox = sinon.createSandbox()

  let storeList

  beforeEach(() => {
    setActivePinia(createPinia())
    storeList = require('./StoreRegionList').default
  })
  afterEach(() => {
    sandbox.restore()
    resetModules()
  })

  it('loads', () => {
    assert(storeList)
  })

  it('can render', () => {
    const regionName = 'Test Region Name'
    const wrapper = mount(storeList, {
      localVue,
      propsData: {
        regionName,
        stores: [createMockStore()],
      },
    })
    assert.notStrictEqual(wrapper.vm.$el.innerHTML.indexOf(regionName), -1)
  })
})

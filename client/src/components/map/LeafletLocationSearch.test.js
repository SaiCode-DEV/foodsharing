import { mount, createLocalVue } from '@vue/test-utils'
import LeafletLocationSearch from './LeafletLocationSearch.vue'
import i18n from '@/helper/i18n'
import assert from 'assert'
import AddressSearchField from './AddressSearchField.vue'
import LeafletLocationPicker from './LeafletLocationPicker.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import { BFormGroup } from 'bootstrap-vue'

const localVue = createLocalVue()

localVue.$i18n = (key, variables = {}) => {
  return i18n(key, variables)
}

describe('LeafletLocationSearch', () => {
  it('should render', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
      },
    })
    assert(wrapper.vm.$el)
  })

  it('should render Markdown', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
        additionalInfoText: 'test',
      },
    })
    const markdown = wrapper.findComponent(Markdown)
    assert(markdown.exists())
  })

  it('should render AddressSearchField', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
      },
    })
    const addressSearchField = wrapper.findComponent(AddressSearchField)
    assert(addressSearchField.exists())
  })

  it('should render LeafletLocationPicker', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
      },
    })
    const leafletLocationPicker = wrapper.findComponent(LeafletLocationPicker)
    assert(leafletLocationPicker.exists())
  })

  it('should render address fields', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
      },
    })
    const bFormGroup = wrapper.findAllComponents(BFormGroup)
    assert(bFormGroup.length === 4)
  })

  it('should not render address fields', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
        showAddressFields: false,
      },
    })
    const bFormGroup = wrapper.findAllComponents(BFormGroup)
    assert(!bFormGroup.exists())
  })

  it('should render address fields', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
      },
    })
    const bFormGroup = wrapper.findAllComponents(BFormGroup)
    assert(bFormGroup.length === 4)
  })

  it('should set address if coords yield valid location data', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
      },
    })
    const streetInput = wrapper.findComponent({ ref: 'inputStreet' })
    assert(streetInput.exists())
    // TODO: get valid location data response via mock call - check that the address is updated
  })

  it('should not set address if coords yield valid location data', () => {
    const wrapper = mount(LeafletLocationSearch, {
      localVue,
      propsData: {
        zoom: 17,
        coordinates: { lat: 50, lon: 10 },
      },
    })
    const streetInput = wrapper.findComponent({ ref: 'inputStreet' })
    assert(streetInput.exists())
    // TODO: get invalid location data response via mock call - check that the address is not updated
  })
})

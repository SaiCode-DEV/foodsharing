import { mount, createLocalVue } from '@vue/test-utils'
import NavNotificationsEntry from './NavNotificationsEntry.vue'
import Avatar from '@/components/Avatar/Avatar.vue'
import TimeDisplay from '@/components/TimeDisplay.vue'
import i18n from '@/helper/i18n'
import { BButton } from 'bootstrap-vue'
import assert from 'assert'
import sinon from 'sinon'

const createContainer = (tag = 'div') => {
  const container = document.createElement(tag)
  document.body.appendChild(container)

  return container
}

const localVue = createLocalVue()

localVue.prototype.$t = (key, variables = {}) => {
  return i18n(key, variables)
}

const bell = {
  isRead: false,
  isDeleting: false,
  isCloseable: true,
  id: 'test',
  image: 'test-image',
  title: 'banana_given',
  payload: {
    name: 'test',
  },
  createdAt: Date.now(),
  key: 'store_new_request',
}

const remove = sinon.spy()
const read = sinon.spy()

// necessary mock because "@/script" is used in the component and that fact leads to a wrong "isMob()" assertion
Object.defineProperty(window, 'innerWidth', {
  configurable: true,
  writable: true,
  value: 800,
})

describe('NavNotificationsEntry', () => {
  it('should render', () => {
    const wrapper = mount(NavNotificationsEntry, {
      localVue,
      attachTo: createContainer(),
      propsData: {
        bell,
      },
    })
    assert(wrapper.vm.$el)
  })

  it('should render Avatar', () => {
    const wrapper = mount(NavNotificationsEntry, {
      localVue,
      attachTo: createContainer(),
      propsData: {
        bell,
      },
    })
    const avatar = wrapper.findComponent(Avatar)
    assert(avatar.exists())
  })

  it('should trigger remove event on Avatar click', async () => {
    const wrapper = mount(NavNotificationsEntry, {
      localVue,
      attachTo: createContainer(),
      propsData: {
        bell,
      },
      listeners: {
        remove,
      },
    })
    const avatar = wrapper.findComponent(Avatar)

    await avatar.trigger('click')
    assert(remove.calledOnce)
  })

  it('should render Time', () => {
    const wrapper = mount(NavNotificationsEntry, {
      localVue,
      attachTo: createContainer(),
      propsData: {
        bell,
      },
    })
    const time = wrapper.findComponent(TimeDisplay)
    assert(time.exists())
  })

  it('should render Button', () => {
    const wrapper = mount(NavNotificationsEntry, {
      localVue,
      attachTo: createContainer(),
      propsData: {
        bell,
      },
    })
    const button = wrapper.findComponent(BButton)
    assert(button.exists())
  })

  it('should trigger read event <a> click', async () => {
    const wrapper = mount(NavNotificationsEntry, {
      localVue,
      attachTo: createContainer(),
      propsData: {
        bell,
      },
      listeners: {
        read,
      },
    })
    const a = wrapper.find('a')

    await a.trigger('click')
    assert(read.calledOnce)
    assert(read.calledWith(bell))
  })
})

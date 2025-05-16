import sinon from 'sinon'
import assert from 'assert'
import { resetModules } from '>/utils'
import * as browserModule from '@/browser'

describe('script', () => {
  const sandbox = sinon.createSandbox()

  let browser
  let script
  let server

  beforeEach(() => {
    server = sinon.createFakeServer()

    browser = Object.create(browserModule)
    Object.defineProperty(browser, 'isMob', {
      configurable: true,
      writable: true,
      value: browserModule.isMob,
    })

    const browserPath = require.resolve('@/browser')
    require.cache[browserPath] = {
      exports: browser,
    }

    script = require('@/script')
  })

  afterEach(() => {
    sandbox.verifyAndRestore()
    server.restore()
    sandbox.restore()
    resetModules()
  })

  describe('on mobile', () => {
    beforeEach(() => {
      browser.isMob = () => true
    })

    describe('isMob', () => {
      it('works', () => {
        assert.strictEqual(script.isMob(), true)
      })
    })
  })

  describe('on desktop', () => {
    beforeEach(() => {
      browser.isMob = () => false
    })

    it('is not mobile!', () => {
      assert.strictEqual(script.isMob(), false)
    })
  })
})

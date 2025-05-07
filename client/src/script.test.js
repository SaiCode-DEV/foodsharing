import sinon from 'sinon'
import assert from 'assert'
import { sleep, resetModules } from '>/utils'
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

    describe('pulse', () => {
      let info
      let success
      let error
      beforeEach(() => {
        document.body.innerHTML = `
            <div class="pulse-msg ui-shadow ui-corner-all" id="pulse-error" style="display:none;"></div>
            <div class="pulse-msg ui-shadow ui-corner-all" id="pulse-info" style="display:none;"></div>
            <div class="pulse-msg ui-shadow ui-corner-all" id="pulse-success" style="display:none;"></div>
        `

        info = document.getElementById('pulse-info')
        success = document.getElementById('pulse-success')
        error = document.getElementById('pulse-error')
      })
      afterEach(() => {
        for (const el of [info, success, error]) {
          el.style.display = 'none'
        }
      })
      it('can show info', () => {
        const message = 'a nice info message'
        script.pulseInfo(message, { timeout: 0 })
        assert.strictEqual(info.innerHTML, '<div class="pulse-message">' + message + '</div>')
      })

      it('can show success', () => {
        const message = 'a nice success message'
        script.pulseSuccess(message, { timeout: 0 })
        assert.strictEqual(success.innerHTML, '<div class="pulse-message">' + message + '</div>')
      })

      it('can show error', () => {
        const message = 'a nice error message'
        script.pulseError(message, { timeout: 0 })
        assert.strictEqual(error.innerHTML, '<div class="pulse-message">' + message + '</div>')
      })

      it('will be hidden after a timeout', async () => {
        const message = 'a nice message'
        assert.strictEqual(info.style.display, 'none')
        script.pulseInfo(message, { timeout: 0 })
        assert(['block', ''].includes(info.style.display))
        await sleep(20)
        assert.strictEqual(info.style.display, 'none')
      })
    })
  })
})

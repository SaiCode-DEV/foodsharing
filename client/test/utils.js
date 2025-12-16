/* global beforeEach, afterEach */
import sinon from 'sinon'

export function jsonOK (data) {
  return [200, { 'Content-Type': 'application/json' }, JSON.stringify(data)]
}

export function sleep (ms) {
  return new Promise(resolve => setTimeout(resolve, ms))
}

export function resetModules () {
  Object.keys(require.cache).forEach(entry => {
    if (entry.startsWith('./src')) {
      delete require.cache[entry]
    }
  })
}

export function later (fn) {
  return new Promise((resolve, reject) => {
    process.nextTick(() => {
      try {
        fn()
        resolve()
      } catch (err) {
        reject(err)
      }
    })
  })
}

let GETParams

export function setGETParams (params) {
  Object.assign(GETParams, params)
}

const sandbox = sinon.createSandbox()

if (!window.showLoading) {
  window.showLoading = () => {}
}

beforeEach(() => {
  GETParams = {}
  sandbox.stub(window, 'showLoading').returns(true)
})

afterEach(() => {
  sandbox.restore()
})

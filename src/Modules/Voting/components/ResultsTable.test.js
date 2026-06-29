/* global describe, it */
import { mount, createLocalVue } from '@vue/test-utils'
import '@/vue'
import i18n from '@/helper/i18n'

const assert = require('assert')

const localVue = createLocalVue()
localVue.prototype.$t = (key, variables = {}) => i18n(key, variables)

describe('ResultsTable', () => {
  function mountTable () {
    const ResultsTable = require('./ResultsTable').default
    // Two options whose vote sums are 0 and 5. Each option has more than one
    // value so the "sum" column is shown (numValues > 1).
    const options = [
      { text: 'A', values: { 1: 0, '-1': 0 } }, // sum = 0
      { text: 'B', values: { 1: 5, '-1': 0 } }, // sum = 5
    ]
    const wrapper = mount(ResultsTable, {
      localVue,
      propsData: { options, numVotes: 5 },
    })
    // Force a desktop width so the (viewIsMD-only) "sum" column is present.
    wrapper.setData({ windowWidth: 1024 })
    return { wrapper, options }
  }

  it('sorts the "sum" column by the numeric value, not the signed string (#2563)', () => {
    const { wrapper, options } = mountTable()
    const sumField = wrapper.vm.tableFields.find(f => f.key === 'sum')

    assert.ok(sumField, 'the sum column should be present')
    // Before the fix this was the string 'true' (sort by the "+5"/"0" label),
    // which sorted lexicographically. It must be a function returning the
    // numeric sum so the table sorts numerically.
    assert.strictEqual(typeof sumField.sortByFormatted, 'function')
    assert.strictEqual(sumField.sortByFormatted(null, 'sum', options[0]), 0)
    assert.strictEqual(sumField.sortByFormatted(null, 'sum', options[1]), 5)
  })
})

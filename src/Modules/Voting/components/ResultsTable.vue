<template>
  <div class="bootstrap">
    <div class="my-1 mb-2">
      <b>{{ $t('poll.results.number_of_votes') }}</b>: {{ numVotes }}
    </div>

    <b-table
      :fields="tableFields"
      :items="options"
      primary-key="optionIndex"
      small
      hover
      responsive
      striped
      sort-by="optionText"
      :sort-desc="false"
    >
      <template v-if="numValues !== 7" #head(value1)>
        <span v-if="numValues === 1" v-text="$t('poll.results.votes')" />
        <span v-else-if="numValues === 3">
          <i class="fas fa-thumbs-up" /> (+1)
        </span>
      </template>
      <template v-if="numValues === 3" #head(value0)>
        <i class="fas fa-meh" /> (0)
      </template>
      <template v-if="numValues === 3" #head(value-1)>
        <i class="fas fa-thumbs-down" /> (-1)
      </template>

      <template v-if="numValues > 1" #head(sum)>
        <span
          v-b-tooltip="viewIsLG ? '' : $t('poll.results.sum')"
          v-text="$t(`poll.results.${viewIsLG ? 'sum' : 'sumShort'}`)"
        />
      </template>
      <template v-if="numValues > 1" #head(average)>
        <span
          v-b-tooltip="viewIsLG ? '' : $t('poll.results.average')"
          v-text="$t(`poll.results.${viewIsLG ? 'average' : 'averageShort'}`)"
        />
      </template>
      <template v-if="numValues > 1" #head(standardDeviation)>
        <span
          v-b-tooltip="viewIsXL ? '' : $t('poll.results.standardDeviation')"
          v-text="$t(`poll.results.${viewIsXL ? 'standardDeviation' : 'standardDeviationShort'}`)"
        />
      </template>

      <template #cell(text)="row">
        <Markdown :source="row.item.text" />
      </template>
    </b-table>
  </div>
</template>

<script>

import Markdown from '@/components/Markdown/Markdown.vue'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: { Markdown },
  mixins: [MediaQueryMixin],
  props: {
    options: {
      type: Array,
      required: true,
    },
    numVotes: {
      type: Number,
      required: true,
    },
  },
  computed: {
    numValues () {
      return Object.entries(this.options[0].values).length
    },
    tableFields () {
      const result = [
        {
          key: 'text',
          sortable: true,
          sortByFormatted: 'true',
          label: this.$t('poll.results.option_text'),
          class: 'align-left',
        },
      ]

      const entries = Object.entries(this.options[0].values).sort(function (a, b) {
        return b[0] - a[0]
      })
      entries.forEach(v => {
        result.push({
          key: 'value' + v[0],
          label: this.withSign(v[0]),
          sortable: true,
          sortByFormatted: 'true',
          formatter: (value, key, item) => item.values[v[0]],
        })
      })

      if (this.numValues > 1) {
        if (this.viewIsMD) {
          result.push({
            key: 'sum',
            sortable: true,
            sortByFormatted: 'true',
            class: 'text-center',
            formatter: (value, key, item) => this.withSign(this.sumVotes(item)),
          })
        }
        result.push({
          key: 'average',
          sortable: true,
          sortByFormatted: 'true',
          class: 'text-center',
          formatter: (value, key, item) => this.withSign(this.round(this.averageVotes(item))),
        })
        if (this.viewIsMD) {
          result.push({
            key: 'standardDeviation',
            class: 'text-center',
            formatter: (value, key, item) => this.round(this.standardDeviationVotes(item)),
          })
        }
      }
      return result
    },
  },
  methods: {
    sumVotes (option) {
      let sum = 0
      for (const v in option.values) {
        sum += v * option.values[v]
      }
      return sum
    },
    averageVotes (option) {
      const sum = this.sumVotes(option)
      return sum / this.numVotes
    },
    standardDeviationVotes (option) {
      const average = this.averageVotes(option)
      let varianceSum = 0
      for (const v in option.values) {
        const count = option.values[v]
        varianceSum += count * (v - average) ** 2
      }

      const variance = varianceSum / this.numVotes
      return Math.sqrt(variance)
    },
    round (value) {
      if (isNaN(value)) return '---'
      return Math.round(value * 100) / 100
    },
    withSign (value) {
      return value > 0 ? `+${value}` : value
    },
  },
}
</script>

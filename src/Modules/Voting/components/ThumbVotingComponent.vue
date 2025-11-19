<template>
  <div class="bootstrap">
    <b-form-group
      v-for="(option, i) in options"
      :key="option.optionIndex"
    >
      <b-row>
        <b-col>
          <Markdown :source="option.text" />
        </b-col>
        <b-col>
          <b-form-radio
            v-model="selected[i]"
            v-b-tooltip.hover
            :title="$t('poll.type_2.tooltip_positive')"
            value="1"
            button
            button-variant="outline-secondary"
            :disabled="!enabled"
          >
            <i class="fas fa-thumbs-up" />
          </b-form-radio>
          <b-form-radio
            v-model="selected[i]"
            v-b-tooltip.hover
            :title="$t('poll.type_2.tooltip_neutral')"
            value="0"
            button
            button-variant="outline-secondary"
            :disabled="!enabled"
          >
            <i class="fas fa-meh" />
          </b-form-radio>
          <b-form-radio
            v-model="selected[i]"
            v-b-tooltip.hover
            :title="$t('poll.type_2.tooltip_negative')"
            value="-1"
            button
            button-variant="outline-secondary"
            :disabled="!enabled"
          >
            <i class="fas fa-thumbs-down" />
          </b-form-radio>
        </b-col>
      </b-row>
    </b-form-group>
  </div>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    options: {
      type: Array,
      required: true,
    },
    enabled: {
      type: Boolean,
      default: true,
    },
  },
  data () {
    return {
      selected: Array(this.options.length).fill(null),
    }
  },
  computed: {
    isValidSelection: function () {
      return !this.selected.includes(null)
    },
    votingRequestValues: function () {
      const v = {}
      for (let i = 0; i < this.selected.length; i++) {
        v[this.options[i].optionIndex] = this.selected[i]
      }
      return v
    },
  },
  watch: {
    isValidSelection () { this.$emit('update-valid-selection', this.isValidSelection) },
    votingRequestValues () { this.$emit('update-voting-request-values', this.votingRequestValues) },
  },
  mounted () {
    this.$emit('update-valid-selection', this.isValidSelection)
    this.$emit('update-voting-request-values', this.votingRequestValues)
  },
}
</script>

<style scoped lang="scss">
  .btn .fas {
    vertical-align: middle;
    margin-right: 0.5rem;
  }
  .btn .fas:last-child {
    margin-right: 0;
  }
</style>

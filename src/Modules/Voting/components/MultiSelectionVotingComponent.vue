<template>
  <div class="bootstrap">
    <b-form-group>
      <b-form-checkbox
        v-for="option in options"
        :key="option.optionIndex"
        v-model="selected"
        :value="option.optionIndex"
        :disabled="!enabled"
        @change="votingRequestValues.update"
      >
        <Markdown :source="option.text" />
      </b-form-checkbox>
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
      selected: [],
    }
  },
  computed: {
    // only the selected options are needed for the REST request
    votingRequestValues: function () {
      const v = {}
      for (let i = 0; i < this.selected.length; i++) {
        v[this.selected[i]] = 1
      }
      return v
    },
  },
  watch: {
    votingRequestValues () { this.$emit('update-voting-request-values', this.votingRequestValues) },
  },
  created () {
    this.$emit('update-valid-selection', true)
    this.$emit('update-voting-request-values', this.votingRequestValues)
  },
}
</script>

<style scoped lang="scss">
</style>

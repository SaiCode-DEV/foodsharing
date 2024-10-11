<template>
  <div class="bootstrap">
    <b-form-group class="flex-wrap">
      <b-form-row
        v-for="i in options.length"
        :key="i"
        class="mb-5"
      >
        <b-col class="min-width-250 pb-2">
          <Markdown :source="options[i - 1].text" />
        </b-col>
        <b-col class="min-width-250">
          <vue-slider
            v-model="selected[i-1]"
            :min="-3"
            :max="3"
            :marks="marks"
            :adsorb="true"
            :disabled="!enabled"
            tooltip="none"
            :class="{'untouched-slider': selected[i-1] === null}"
          />
        </b-col>
      </b-form-row>
    </b-form-group>
  </div>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown.vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'

export default {
  components: { Markdown, VueSlider },
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
      marks: [-3, -2, -1, 0, 1, 2, 3],
    }
  },
  computed: {
    votingRequestValues: function () {
      const v = {}
      for (let i = 0; i < this.selected.length; i++) {
        v[this.options[i].optionIndex] = this.selected[i]
      }
      return v
    },
    selectionValid () {
      return !this.selected.some(s => s === null)
    },
  },
  watch: {
    votingRequestValues () { this.$emit('update-voting-request-values', this.votingRequestValues) },
    selectionValid () { this.$emit('update-valid-selection', this.selectionValid) },
  },
  created () {
    this.$emit('update-voting-request-values', this.votingRequestValues)
  },
}
</script>

<style lang="scss">

.untouched-slider{
  .vue-slider-dot {
    display: none;
  }
  .vue-slider-mark-step-active {
    box-shadow: 0 0 0 2px #e8e8e8 !important;
  }
}

.min-width-250 {
  min-width: 250px;
}

</style>

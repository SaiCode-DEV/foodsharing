<!-- This component can be used as a wrapper providing a responsic base layout using up to 3 columns (depending on screen width and content).
 This should be used as a replacement for addContent from PageHelper.php
-->
<template>
  <div class="mx-2 mx-sm-0">
    <slot name="top" />
    <div class="row">
      <div v-if="$slots.left" :class="colClasses.left">
        <slot name="left" />
        <slot v-if="!(thirdColumnBreakpoint || !secondColumnBreakpoint)" name="right" />
      </div>
      <div class="col" style="min-height: 100px;">
        <slot />
      </div>
      <div v-if="$slots.right && (!$slots.left || thirdColumnBreakpoint || !secondColumnBreakpoint)" :class="colClasses.right">
        <slot name="right" />
      </div>
    </div>
  </div>
</template>
<script>
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  mixins: [MediaQueryMixin],
  props: {
    wideCols: { type: Boolean, default: false },
  },
  computed: {
    colClasses () {
      if (this.wideCols) {
        return { left: 'col-xl-3 col-lg-5', right: 'col-xl-3' }
      }
      if (this.$slots.left && this.$slots.right) {
        return { left: 'col-xl-3 col-lg-4 col-md-5', right: 'col-xl-3' }
      }
      return { left: 'col-lg-4 col-md-5', right: 'col-lg-4 col-md-5' }
    },
    thirdColumnBreakpoint () {
      return this.wideCols ? this.viewIsXXL : this.viewIsXL
    },
    secondColumnBreakpoint () {
      return this.wideCols ? this.viewIsLG : this.viewIsMD
    },
  },
}
</script>

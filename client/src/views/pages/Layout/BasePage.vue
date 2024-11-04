<!-- This component can be used as a wrapper providing a responsic base layout using up to 3 columns (depending on screen width and content).
 This should be used as a replacement for addContent from PageHelper.php
-->
<template>
  <div class="mx-2 mx-sm-0">
    <slot name="top" />
    <div class="row">
      <div v-if="$slots.left" :class="colClasses.left">
        <slot name="left" />
        <slot v-if="!(viewIsXL || !viewIsMD)" name="right" />
      </div>
      <div class="col" style="min-height: 100px;">
        <slot />
      </div>
      <div v-if="$slots.right" :class="colClasses.right">
        <slot v-if="!$slots.left || viewIsXL || !viewIsMD" name="right" />
      </div>
    </div>
  </div>
</template>
<script>
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  mixins: [MediaQueryMixin],
  computed: {
    colClasses () {
      if (this.$slots.left && this.$slots.right) {
        return { left: 'col-xl-3 col-lg-4 col-md-5', right: 'col-xl-3' }
      }
      return { left: 'col-xxl-3 col-lg-4 col-md-5', right: 'col-xxl-3 col-lg-4 col-md-5' }
    },
  },
}
</script>

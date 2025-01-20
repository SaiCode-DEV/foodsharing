<template>
  <b-alert
    show
    variant="success"
    class="d-flex"
  >
    <div class="flex-grow-1 align-self-center">
      <h1>
        <span class="logo">
          <span>food</span><span>sharing</span>
        </span>
        {{ props.regionData.name }}
      </h1>
    </div>
    <div>
      <Fork class="fork" />
    </div>
  </b-alert>
</template>
<script setup>
import { defineProps } from 'vue'
import Fork from '../Index/fork.vue'

const props = defineProps({
  regionData: { type: Object, required: true },
})
</script>
<style lang="scss" scoped>
.logo span:first-child {
  color: var(--fs-color-primary-500);
}
.logo span:last-child {
  color: var(--fs-color-secondary-500);
}
.fork {
  height: 8em;
  float: right;
  margin: -1em;
}
</style>

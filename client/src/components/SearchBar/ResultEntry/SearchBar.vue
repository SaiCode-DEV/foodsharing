<template>
  <div class="search-bar-wrapper">
    <label
      v-if="!props.inputId"
      class="sr-only"
      for="searchField"
      v-text="$t(props.placeholder)"
    />
    <i class="icon fas" :class="props.isLoading ? 'fa-spinner fa-spin' : 'fa-search'" />
    <b-form-input
      :id="props.inputId || 'searchField'"
      ref="searchField"
      :value="props.query"
      type="text"
      class="form-control"
      :placeholder="$t(props.placeholder)"
      tabindex="1"
      :debounce="debounce"
      @update="newValue => $emit('update:query', newValue)"
    />
    <i
      v-if="props.query.length > 0"
      class="icon icon-right fas fa-times cursor-pointer"
      @click="$emit('update:query', '')"
    />
  </div>
</template>
<script setup>
import { defineProps, ref, defineExpose } from 'vue'

const props = defineProps({
  inputId: { type: String, default: null },
  query: { type: String, default: '' },
  isLoading: { type: Boolean, default: false },
  placeholder: { type: String, default: 'search.placeholder' },
  debounce: { type: Number, default: 150 },
})

const searchField = ref(null)

defineExpose({
  focus () {
    searchField.value.select()
  },
})
</script>
<style lang="scss" scoped>
.cursor-pointer {
  cursor: pointer;
}
.search-bar-wrapper {
  display: flex;
  position: relative;
  flex-grow: 1;
  align-items: center;
}

.icon {
  position: absolute;
  left: .25rem;
  font-size: 1.15rem;
  color: var(--fs-color-dark);
}

.icon-right {
  left: unset;
  right: 0;
}
::v-deep.form-control {
  padding-left: 2.75rem;
  padding-right: 2rem;
}
</style>

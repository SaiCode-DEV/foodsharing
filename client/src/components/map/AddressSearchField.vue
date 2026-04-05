<!-- A text field combined with geocoding that allows searching for addresses. The selected suggestion is emitted as a 'change' event. -->
<template>
  <div class="my-2 position-relative location-search">
    <SearchBar
      :input-id="props.inputId"
      :query="query"
      :is-loading="autocompleteLoading > 0"
      placeholder="addresspicker.placeholder"
      @update:query="fetchSuggestions"
    />
    <b-list-group class="location-options">
      <b-list-group-item
        v-for="(suggestion, i) in suggestions"
        :key="i"
        button
        tabindex="1"
        @click="selectSuggestion(suggestion)"
      >
        {{ suggestion.formatted }}
      </b-list-group-item>
    </b-list-group>
  </div>
</template>

<script setup>
import { fetchAutocomplete } from '@/api/geocode'

import { defineProps, defineEmits, ref, defineExpose } from 'vue'
import SearchBar from '../SearchBar/ResultEntry/SearchBar.vue'

const props = defineProps({
  inputId: { type: String, default: null },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  initialQuery: { type: String, default: '' },
})

const emit = defineEmits(['change'])

const suggestions = ref([])
const autocompleteLoading = ref(0)
const query = ref(props.initialQuery)

async function fetchSuggestions (input) {
  query.value = input
  autocompleteLoading.value++
  try {
    const locations = await fetchAutocomplete(input)
    if (input !== query.value) return // discard outdated queries
    suggestions.value = []
    if (!locations?.length) return
    if (locations.length === 1) {
      emit('change', locations[0])
    }
    suggestions.value = locations
  } finally {
    autocompleteLoading.value--
  }
}

function selectSuggestion (suggestion) {
  query.value = suggestion.formatted
  document.activeElement.blur()
  emit('change', suggestion)
}

defineExpose({
  setSearchString: function (location) {
    query.value = location.formatted
    suggestions.value = [location]
  },
})
</script>

<style lang="scss" scoped>
.location-search:focus-within .location-options {
  display: block;
}
.location-options {
  position:absolute;
  z-index: 1020;
  width: 100%;
  display: none;
  button:focus {
    outline: none;
    background-color: var(--fs-color-gray-200);
  }
}
</style>

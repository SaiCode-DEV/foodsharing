<!-- A text field combined with geocoding that allows searching for addresses. The selected suggestion is emitted as a 'change' event. -->
<template>
  <div class="my-2 position-relative location-search">
    <b-form-input
      id="searchinput"
      v-model="searchInput"
      debounce="100"
      autocomplete="off"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      @input="fetchSuggestions"
    />
    <b-list-group class="location-options">
      <b-list-group-item
        v-for="(suggestion, i) in suggestions"
        :key="i"
        button
        @click="selectSuggestion(suggestion)"
      >
        {{ suggestion.formatted }}
      </b-list-group-item>
    </b-list-group>
  </div>
</template>

<script setup>
import { BFormInput } from 'bootstrap-vue'
import { fetchAutocomplete } from '@/api/geocode'

import { defineProps, defineEmits, ref, defineExpose } from 'vue'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
})

const emit = defineEmits(['change'])

const suggestions = ref([])
const autocompleteLoading = ref(false)
const searchInput = ref(null)

async function fetchSuggestions (input) {
  suggestions.value = []
  if (!autocompleteLoading.value) {
    autocompleteLoading.value = true
    const locations = await fetchAutocomplete(input)
    if (!locations?.length) {
      autocompleteLoading.value = false
      return
    }
    if (locations.length === 1) {
      emit('change', locations[0])
    }
    suggestions.value = locations
    autocompleteLoading.value = false
  }
}

function selectSuggestion (suggestion) {
  searchInput.value = suggestion.formatted
  document.activeElement.blur()
  emit('change', suggestion)
}

defineExpose({
  setSearchString: function (value) {
    searchInput.value = value
  },
})
</script>

<style scoped>
.location-search:focus-within .location-options {
  display: block;
}
.location-options {
  position:absolute;
  z-index: 1020;
  width: 100%;
  display: none;
}

</style>

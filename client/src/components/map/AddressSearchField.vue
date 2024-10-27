<!-- A text field combined with geocoding that allows searching for addresses. The selected suggestion is emitted as a 'change' event. -->
<template>
  <div>
    <b-form-input
      id="searchinput"
      v-model="searchInput"
      list="suggestions"
      debounce="100"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      @input="fetchSuggestions"
    />
    <!-- TODO: Datalist is Buggy! Use (Vuetify) Autocomplete! -->
    <datalist id="suggestions">
      <option
        v-for="(suggestion, index) in suggestions"
        :key="index"
      >
        {{ suggestion.formatted }}
      </option>
    </datalist>
  </div>
</template>

<script setup>
import { BFormInput } from 'bootstrap-vue'
import { fetchAutocomplete } from '@/api/geocode'

import { defineProps, defineEmits, ref } from 'vue'

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
    fetchAutocomplete(input)
      .then((data) => {
        if (data?.length === 0) {
          autocompleteLoading.value = false
          return
        }
        suggestions.value = data
        updateMap(suggestions.value[0])
      })
      .finally(() => {
        autocompleteLoading.value = false
      })
  }
}

/**
 * This function is called when a suggestion was selected in the search field.
 */
function updateMap (searchResult) {
  // update the address data
  if (!searchResult) {
    return
  }
  const coords = { lat: searchResult.lat, lon: searchResult.lon }
  emit('change', coords)
}

</script>

<style scoped>

</style>

<template>
  <div>
    <b-container class="bg-white p-4">
      <b-form>
        <b-form-group
          label="In welchem Bezirk befindet sich der Fairteiler?"
          label-for="district-select"
          required
        >
          <b-form-select
            id="district-select"
            v-model="formData.regionId"
            :options="regionOptions"
            required
          />
        </b-form-group>

        <b-form-group label="Name" label-for="name-input">
          <b-form-input
            id="name-input"
            v-model="formData.name"
            type="text"
            required
            placeholder="Geben Sie den Namen ein"
          />
        </b-form-group>

        <b-form-group label="Beschreibung" label-for="description-md">
          <MarkdownInput
            :value.sync="formData.description"
            conceal-toolbar
            variant="outline-primary"
            :rows="2"
          />
        </b-form-group>

        <b-form-group label="Bild" label-for="name-input">
          <FileUploadVForm
            :is-image="true"
            :img-height="900"
            :img-width="400"
            :initial-value="formData.picture"
          />
        </b-form-group>

        <b-form-group label="Adress-/Standort-Suche" label-for="name-input">
          <LeafletLocationSearchVForm
            :coordinates="formData.location"
            :zoom="zoom"
          />
        </b-form-group>

        <label for="tags-basic">Foodsaver:innen, die Ansprechpersonen für den Fairteiler sind</label>
        <multi-user-search-input
          v-model="formData.manager"
          :region-id="formData.regionId"
          button-icon="fa-user-plus"
          :is-value-object="true"
        />

        <b-button variant="secondary" @click="saveFoodSharePoint">
          {{ i18n('button.save') }}
        </b-button>
      </b-form>
    </b-container>
  </div>
</template>

<script setup>
import { onMounted, computed, ref, watch } from 'vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import FileUploadVForm from '@/components/upload/FileUploadVForm.vue'
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm'
import MultiUserSearchInput from '@/components/MultiUserSearchInput.vue'
import { useFoodSharePointStore } from '@/stores/foodSharePoint'
import { useRegionStore } from '@/stores/regions'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { addFoodSharePoint, updateFoodSharePoint } from '@/api/foodsharepoints'

const foodSharePointStore = useFoodSharePointStore()
const regionStore = useRegionStore()
const zoom = 17

const selectedRegionId = ref()
const foodSharePointId = ref()

const formData = ref({
  regionId: selectedRegionId.value,
  name: '',
  description: '',
  picture: null,
  address: '',
  postalCode: '',
  city: '',
  location: { lat: null, lon: null },
  managers: [],
})

watch(() => foodSharePointStore.foodSharePoint, (newValue) => {
  if (newValue) {
    formData.value = {
      regionId: selectedRegionId.value,
      name: newValue.name,
      description: newValue.description,
      picture: newValue.picture,
      address: newValue.address,
      postalCode: newValue.postcode,
      city: newValue.city,
      location: { lat: newValue.lat, lon: newValue.lon },
      managers: newValue.managers,
    }
  }
}, { immediate: true })

async function saveFoodSharePoint () {
  showLoader()
  try {
    if (foodSharePointId) {
      await updateFoodSharePoint(foodSharePointId, formData.value)
      pulseSuccess(i18n('blog.success.edit'))
    } else {
      await addFoodSharePoint(formData.value)
      pulseSuccess(i18n('blog.success.new'))
    }
  } catch (error) {
    console.error('saveBlogPost', error)
    pulseError(i18n('blog.failure.edit'))
  } finally {
    hideLoader()
  }
}

const regionOptions = computed(() => {
  return regionStore.regions.map(region => ({
    value: region.id,
    text: region.name,
  }))
})

onMounted(() => {
  const url = new URL(window.location.href)
  const searchParams = new URLSearchParams(url.search)
  selectedRegionId.value = parseInt(searchParams.get('bid'))
  foodSharePointId.value = parseInt(searchParams.get('id'))
  if (foodSharePointId.value) {
    foodSharePointStore.fetchFoodSharePoint(foodSharePointId.value)
  }
})
</script>

<style scoped lang="scss">

</style>

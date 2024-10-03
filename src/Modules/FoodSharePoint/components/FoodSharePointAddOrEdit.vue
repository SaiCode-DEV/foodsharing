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
          <file-upload
            :is-image="true"
            :img-height="400"
            :img-width="900"
            :filename="formData.picture"
            @change="value => formData.picture = value.url"
          />
        </b-form-group>

        <b-form-group label="Adress-/Standort-Suche" label-for="name-input">
          <leaflet-location-search
            v-if="dataLoaded"
            :coordinates="formData.location"
            :postal-code="formData.postalCode"
            :street="formData.address"
            :city="formData.city"
            :zoom="zoom"
            @address-change="onAddressChanged"
          />
        </b-form-group>

        <label for="tags-basic">Foodsaver:innen, die Ansprechpersonen für den Fairteiler sind</label>
        <multi-user-search-input
          v-model="formData.managerIds"
          :region-id="formData.regionId"
          button-icon="fa-user-plus"
        />

        <b-button variant="secondary" @click="saveFoodSharePoint">
          {{ i18n('button.save') }}
        </b-button>
        <b-button
          variant="outline-danger"
          @click="$bvModal.show('deleteFoodSharePointModal')"
        >
          {{ i18n('fsp.delete') }}
        </b-button>
      </b-form>
    </b-container>
    <b-modal
      id="deleteFoodSharePointModal"
      ref="deleteFoodSharePointModal"
      :title="$i18n('forum.thread.delete')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      cancel-variant="primary"
      ok-variant="outline-danger"
      @ok="removeFoodSharePoint"
    >
      {{ $i18n('fsp.deleteConfirm') }}
    </b-modal>
  </div>
</template>

<script setup>
import { onMounted, computed, ref, defineProps } from 'vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import MultiUserSearchInput from '@/components/MultiUserSearchInput.vue'
import { useRegionStore } from '@/stores/regions'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'
import { addFoodSharePoint, deleteFoodSharePoint, getFoodSharePoint, updateFoodSharePoint } from '@/api/foodsharepoints'
import FileUpload from '@/components/upload/FileUpload.vue'
import LeafletLocationSearch from '@/components/map/LeafletLocationSearch.vue'

const regionStore = useRegionStore()
const zoom = 17

const props = defineProps({
  foodSharePointId: {
    type: Number,
    default: null,
  },
  regionId: {
    type: Number,
    default: null,
  },
})

const dataLoaded = ref(!props.foodSharePointId) // True if creating new

const formData = ref({
  regionId: props.regionId,
  name: '',
  description: '',
  picture: null,
  address: '',
  postalCode: '',
  city: '',
  location: { lat: null, lon: null },
  managerIds: [],
})

async function saveFoodSharePoint () {
  showLoader()
  try {
    if (props.foodSharePointId) {
      await updateFoodSharePoint(props.foodSharePointId, formData.value)
      pulseSuccess(i18n('fsp.editSuccess'))
    } else {
      await addFoodSharePoint(formData.value)
      pulseSuccess(i18n('fsp.addSuccess'))
    }
  } catch (error) {
    console.error('saveBlogPost', error)
    pulseError(i18n(props.foodSharePointId ? 'error_unexpected' : 'fsp.addError'))
  } finally {
    hideLoader()
  }
}

async function removeFoodSharePoint () {
  showLoader()
  try {
    await deleteFoodSharePoint(props.foodSharePointId)
    pulseSuccess(i18n('fsp.deleteSuccess'))
    window.location.href = url('foodsharepoints', props.regionId)
  } catch (error) {
    console.error('removeFoodSharePoint', error)
    pulseError(i18n('error_unexpected'))
  } finally {
    hideLoader()
  }
}

function onAddressChanged (coordinates, street, postalCode, city) {
  formData.value.location = coordinates
  formData.value.address = street
  formData.value.postalCode = postalCode
  formData.value.city = city
}

const regionOptions = computed(() => {
  return regionStore.accessibleRegions.map(region => ({
    value: region.id,
    text: region.name,
  }))
})

onMounted(() => {
  if (!props.foodSharePointId) {
    // Create new foodSharePoint
    return
  }
  showLoader()
  getFoodSharePoint(props.foodSharePointId).then((response) => {
    formData.value = {
      regionId: response.regionId,
      name: response.name,
      description: response.description,
      picture: response.picture,
      address: response.address,
      postalCode: response.postalCode,
      city: response.city,
      location: response.location,
      managerIds: response.manager.map(x => x.id),
    }
    hideLoader()
    dataLoaded.value = true // Data is now loaded
  })
})
</script>

<style scoped lang="scss">

</style>

<template>
  <div>
    <Container :collapsible="false" :title="title">
      <div class="list-group-item">
        <b-form-group
          :label="$i18n('fsp_bezirk_id')"
          label-for="district-select"
          required
        >
          <b-form-select
            id="district-select"
            v-model="formData.regionId"
            :options="regionOptions"
            required
          />
          <span v-if="errors.regionId" class="error-message">{{ errors.regionId }}</span>
        </b-form-group>

        <b-form-group
          :label="$i18n('name')"
          label-for="name-input"
        >
          <b-form-input
            id="name-input"
            v-model="formData.name"
            type="text"
            required
            placeholder="Geben Sie den Namen ein"
          />
          <span v-if="errors.name" class="error-message">{{ errors.name }}</span>
        </b-form-group>

        <b-form-group :label="$i18n('desc')" label-for="description-md">
          {{ $i18n('fsp.descLabel') }}
          <MarkdownInput
            id="description-md"
            :value.sync="formData.description"
            conceal-toolbar
            variant="outline-primary"
            :rows="2"
          />
          <span v-if="errors.description" class="error-message">{{ errors.description }}</span>
        </b-form-group>

        <b-form-group :label="$i18n('picture')">
          <file-upload
            :is-image="true"
            :img-height="169"
            :img-width="525"
            :filename="formData.picture"
            @change="value => formData.picture = value.url"
          />
          <span v-if="errors.picture" class="error-message">{{ errors.picture }}</span>
        </b-form-group>

        <b-form-group :label="$i18n('addresspicker.label')">
          <leaflet-location-search
            v-if="dataLoaded"
            :coordinates="formData.location"
            :postal-code="formData.postalCode"
            :street="formData.address"
            :city="formData.city"
            :zoom="zoom"
            @address-change="onAddressChanged"
          />
          <span v-if="errors.address" class="error-message">{{ errors.address }}</span>
        </b-form-group>

        <div v-if="foodSharePointId !== null">
          <label for="fspmanagers-input">{{ $i18n('fspmanagers') }}</label>
          <multi-user-search-input
            id="fspmanagers-input"
            v-model="formData.managerIds"
            :region-id="formData.regionId"
            button-icon="fa-user-plus"
          />
          <span v-if="errors.managerIds" class="error-message">{{ errors.managerIds }}</span>
        </div>

        <div class="d-flex justify-content-between m-2">
          <b-button variant="outline-secondary" @click="backToFoodSharePointOverview">
            {{ i18n('button.prev') }}
          </b-button>
          <div>
            <b-button
              v-if="foodSharePointId !== null"
              variant="outline-danger"
              @click="$bvModal.show('deleteFoodSharePointModal')"
            >
              {{ i18n('fsp.delete') }}
            </b-button>
            <b-button variant="primary" @click="saveFoodSharePoint">
              {{ i18n('button.save') }}
            </b-button>
          </div>
        </div>
      </div>
    </Container>

    <b-modal
      v-if="foodSharePointId !== null"
      id="deleteFoodSharePointModal"
      ref="deleteFoodSharePointModal"
      :title="$i18n('fsp.delete')"
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
import Container from '@/components/Container/Container.vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import MultiUserSearchInput from '@/components/MultiUserSearchInput.vue'
import { useRegionStore } from '@/stores/regions'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'
import { addFoodSharePoint, deleteFoodSharePoint, getFoodSharePoint, updateFoodSharePoint } from '@/api/foodsharepoints'
import FileUpload from '@/components/upload/FileUpload.vue'
import LeafletLocationSearch from '@/components/map/LeafletLocationSearch.vue'
import { useUserStore } from '@/stores/user'

const regionStore = useRegionStore()
const userStore = useUserStore()
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
  location: { lat: userStore.getLocations.lat, lon: userStore.getLocations.lon },
  managerIds: [],
})

const title = computed(() => {
  return props.foodSharePointId === null ? i18n('fsp.add') : i18n('fsp.edit')
})

async function saveFoodSharePoint () {
  if (!validateForm()) {
    pulseError(i18n('fsp.form_validation.invalid'))
    return
  }

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
    console.error('saveFoodSharePoint', error)
    pulseError(i18n(props.foodSharePointId ? 'error_unexpected' : 'fsp.addError'))
  } finally {
    hideLoader()
  }
}

const errors = ref({
  regionId: '',
  name: '',
  description: '',
  picture: '',
  address: '',
  postalCode: '',
  city: '',
  location: '',
  managerIds: '',
})

// ToDo: Move custom validation to vuelidate, if vuelidate is latest or min. 2.0.4
const validateForm = () => {
  let isValid = true

  // Reset all errors
  Object.keys(errors.value).forEach(key => {
    errors.value[key] = ''
  })

  // Validate regionId
  if (formData.value.regionId === null) {
    errors.value.regionId = i18n('fsp.form_validation.required', { name: 'regionId' })
    isValid = false
  }

  // Validate name
  if (formData.value.name === null) {
    errors.value.name = i18n('fsp.form_validation.required', { name: 'name' })
    isValid = false
  } else if (formData.value.name.length < 3) {
    errors.value.name = i18n('fsp.form_validation.min_length', { name: 'name', length: 3 })
    isValid = false
  }

  // Validate description
  if (formData.value.description === null) {
    errors.value.description = i18n('fsp.form_validation.required', { name: 'description' })
    isValid = false
  } else if (formData.value.description.length < 10) {
    errors.value.description = i18n('fsp.form_validation.min_length', { name: 'description', length: 10 })
    isValid = false
  }

  // Validate address
  if (formData.value.address === null) {
    errors.value.address = i18n('fsp.form_validation.required', { name: 'address' })
    isValid = false
  }

  // Validate managerIds
  if (props.foodSharePointId !== null && formData.value.managerIds.length === 0) {
    errors.value.managerIds = i18n('fsp.form_validation.required', { name: 'managerIds' })
    isValid = false
  }

  return isValid
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

function backToFoodSharePointOverview () {
  if (props.foodSharePointId) {
    window.location.href = url('foodsharepoint', props.foodSharePointId)
  } else {
    window.location.href = url('foodsharepoints', props.regionId)
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

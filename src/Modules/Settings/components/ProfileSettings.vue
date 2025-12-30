<template>
  <b-form v-if="isLoaded" @submit.prevent="handleSubmit">
    <b-alert :show="!profileData.mayChangeVerifiedData && isMe">
      <p>
        <i class="fas fa-user-pen mr-1" />
        <span v-text="$t('settings.change_data_info.general')" />
      </p>
      <span v-text="$t('settings.change_data_info.verified')" />
      <Info info-key="change_verified_data" :props="{ link: $url('region_forum', settings.region.id )}" />
    </b-alert>
    <b-alert :show="profileData.mayChangeVerifiedData && !isMe">
      <Markdown :source="$t('profile.editNameInfo', {url: $url('editNameInfoUrl')})" />
    </b-alert>
    <div class="row">
      <div class="col-md-6">
        <b-form-group :label="$t('register.login_name')">
          <b-form-input
            id="input-firstname"
            v-model.lazy="settings.firstName"
            :class="{ 'is-invalid': v$.firstName.$error }"
            type="text"
            :disabled="!profileData.mayChangeVerifiedData"
          />
          <div
            v-if="v$.firstName.$error"
            class="invalid-feedback"
          >
            <span v-if="!v$.firstName.required">{{ $t('register.firstname_required') }}</span>
            <span v-if="!v$.firstName.minLength">{{ $t('register.firstname_minLength') }}</span>
            <span v-if="!v$.firstName.maxLength">{{ $t('register.firstname_maxLength') }}</span>
          </div>
        </b-form-group>
      </div>
      <div class="col-md-6">
        <b-form-group :label="$t('register.login_surname')">
          <b-form-input
            id="input-lastname"
            v-model.lazy="settings.lastName"
            :class="{ 'is-invalid': v$.lastName.$error }"
            type="text"
            :disabled="!profileData.mayChangeVerifiedData"
          />
          <div v-if="v$.lastName.$error" class="invalid-feedback">
            <span v-if="!v$.lastName.required">{{ $t('register.lastname_required') }}</span>
            <span v-if="!v$.lastName.minLength">{{ $t('register.lastname_minLength') }}</span>
            <span v-if="!v$.lastName.maxLength">{{ $t('register.lastname_maxLength') }}</span>
          </div>
        </b-form-group>
      </div>

      <div class="col-md-6">
        <b-form-group :label="$t('register.select_your_gender')">
          <b-form-select v-model="settings.gender" :options="genderOptions" />
        </b-form-group>
      </div>
      <div class="col-md-6">
        <b-form-group :label="$t('register.geb_datum')">
          <b-form-input
            id="settings-birthdate-input"
            v-model="birthdayFormatted"
            type="date"
            autocomplete="off"
            :disabled="!profileData.mayChangeVerifiedData"
          />
          <div v-if="!isValidBirthdate" class="invalid-feedback">
            {{ $t('register.error_birthdate') }}
          </div>
        </b-form-group>
      </div>

      <div class="col-md-6">
        <b-form-group :label="$t('terminology.mobile_phone')">
          <PhoneNumberInput
            :input-value="settings.mobile"
            input-name="mobile"
            @update-phone-number="handleValidValue"
          />
        </b-form-group>
      </div>
      <div class="col-md-6">
        <b-form-group :label="$t('terminology.landline')">
          <PhoneNumberInput
            :input-value="settings.phone"
            input-name="phone"
            @update-phone-number="handleValidValue"
          />
        </b-form-group>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <b-form-group :label="$t('settings.general.current_address')">
          <b-input-group>
            <b-form-input
              :value="locationString"
              type="text"
              :disabled="true"
              :name="'address_' + randomSuffix"
            />
            <b-input-group-append>
              <b-button
                id="change-address-button"
                variant="outline-secondary"
                @click="$refs.AddressModal.show()"
              >
                <i class="far fa-edit" />
              </b-button>
            </b-input-group-append>
          </b-input-group>
        </b-form-group>
        <b-form-group v-if="userStore.settings.isOnTeamPage && (isMe || isOrgUser)" :label="$t('position')">
          <b-input v-model="settings.position" />
        </b-form-group>
      </div>

      <div class="col-md-6">
        <div v-if="isMe">
          <b-form-group :label="$t('terminology.profile_picture')">
            <b-button
              variant="outline-secondary"
              block
              @click="$refs.profilePictureModal.show()"
            >
              {{ $t('terminology.profile_picture') }}
            </b-button>
          </b-form-group>
        </div>
        <div v-if="isOrgUser">
          <b-form-group :label="$t('foodsaver.manage.role')">
            <b-form-select
              id="input-role"
              v-model="settings.role"
              :options="roleOptions"
            />
          </b-form-group>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div v-if="userStore.settings.isOnTeamPage && (isMe || isOrgUser)">
          <b-form-group :label="$t('about_me_public')">
            <Markdown :source="$t('foodsaver.about_me_public')" />
            <MarkdownInput
              ref="md-input"
              class="mt-2"
              input-name="about_me_public"
              variant="outline-primary"
              :rows="2"
              :max-rows="4"
              :conceal-toolbar="true"
              :value="settings.aboutMePublic"
              @update:value="newValue => settings.aboutMePublic = newValue"
            />
          </b-form-group>
        </div>
        <div>
          <b-form-group :label="$t('terminology.homeRegion')">
            <b-input-group>
              <b-form-input
                :value="settings.region.name || $t('search.results.user.no_home_region')"
                type="text"
                :disabled="true"
              />
              <b-input-group-append v-if="!isMe">
                <b-button
                  variant="outline-secondary"
                  @click="$refs.homeRegionTree.openModal()"
                >
                  <i class="far fa-edit" />
                </b-button>
              </b-input-group-append>
            </b-input-group>
          </b-form-group>
        </div>
      </div>
      <div class="col-md-6">
        <div v-if="isMe">
          <b-form-group :label="$t('about_me_intern')">
            <Markdown :source="$t('foodsaver.about_me_intern')" />
            <MarkdownInput
              ref="md-input"
              class="mt-2"
              input-name="about_me_intern"
              variant="outline-primary"
              :rows="2"
              :max-rows="4"
              :conceal-toolbar="true"
              :value="settings.aboutMeInternal"
              @update:value="newValue => settings.aboutMeInternal = newValue"
            />
          </b-form-group>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <b-form-group :label="$t('no_automatic_delete')">
          <b-form-select v-model="settings.noAutoDelete" :options="noAutoDeleteOptions" />
        </b-form-group>
      </div>
    </div>

    <div class="row">
      <b-button
        type="submit"
        variant="primary"
        class="ml-3 mr-3"
        :disabled="!isFieldsValid"
      >
        {{ $t('button.save') }}
      </b-button>
      <ProfilePicture
        ref="profilePictureModal"
        :img-height="400"
        :img-width="400"
        :initial-value="settings.photo"
      />
    </div>

    <ProfileAddressModal
      ref="AddressModal"
      :coordinate="settings.coordinate"
      :location="settings.location"
      :zoom="zoom"
      @update-location="handleUpdateLocation"
    />
    <RegionTreeModal
      v-if="!isMe"
      ref="homeRegionTree"
      :value="settings.region"
      input-name="regionId"
      modal-title="storeview.select_related_region"
      :selectable-region-types="selectableRegionTypes"
      @input="updateHomeRegion"
    />
  </b-form>
  <div v-else>
    <b-spinner label="Loading..." />
  </div>
</template>

<script setup>
import { ref, computed, watch, defineProps } from 'vue'
import ProfilePicture from './ProfilePicture.vue'
import { patchUserProfile } from '@/api/user'
import PhoneNumberInput from '@/components/PhoneNumberInput.vue'
import ProfileAddressModal from './ProfileAddressModal.vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import Markdown from '@/components/Markdown/Markdown'
import { useUserStore } from '@/stores/user'
import RegionTreeModal from '@/components/regiontree/RegionTreeModal.vue'
import { SELECTABLE_REGION_TYPES } from '@/stores/regions'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength, maxLength } from '@vuelidate/validators'
import { pulseError, pulseSuccess } from '@/script'
import Info from '@/components/Help/Info.vue'
import i18n from '@/helper/i18n'

const props = defineProps({
  profileData: { type: Object, default: null },
})

const userStore = useUserStore()

const randomSuffix = Date.now()
const zoom = 17

const settings = ref({
  id: null,
  firstName: '',
  lastName: '',
  aboutMePublic: '',
  photo: '',
  gender: null,
  birthday: null,
  role: null,
  mobile: { value: '', valid: true },
  phone: { value: '', valid: true },
  location: {
    street: '',
    postalCode: '',
    city: '',
  },
  coordinate: { lat: null, lon: null },
  aboutMeInternal: '',
  position: '',
  region: { id: null, name: '' },
  noAutoDelete: false,
})

const birthdayFormatted = computed({
  get () {
    if (!settings.value.birthday) return ''
    // Convert  ISO 8601 Datetime to YYYY-MM-DD
    return settings.value.birthday.split('T')[0]
  },
  set (value) {
    settings.value.birthday = value
  },
})

const genderOptions = [
  { value: 1, text: i18n('register.man') ?? '' },
  { value: 2, text: i18n('register.woman') ?? '' },
  { value: 3, text: i18n('register.other') ?? '' },
]
const roleOptions = [
  { value: 0, text: i18n('terminology.role.0') ?? '' },
  { value: 1, text: i18n('terminology.role.1') ?? '' },
  { value: 2, text: i18n('terminology.role.2') ?? '' },
  { value: 3, text: i18n('terminology.role.3') ?? '' },
  { value: 4, text: i18n('terminology.role.4') ?? '' },
]
const noAutoDeleteOptions = [
  { value: false, text: i18n('automatic_delete') ?? '' },
  { value: true, text: i18n('automatic_not_delete') ?? '' },
]

const selectableRegionTypes = SELECTABLE_REGION_TYPES
const isOrgUser = computed(() => userStore.isOrga)
const isMe = computed(() => userStore.getUserId === settings.value.id)
const isLoaded = computed(() => props.profileData !== null && settings.value.id !== null)
const isFieldsValid = computed(() =>
  settings.value.phone.valid && settings.value.mobile.valid && !v$.value.$invalid && isValidBirthdate.value,
)
const isValidBirthdate = computed(() => {
  if (!settings.value.birthday) return false
  let year, month, day
  if (typeof settings.value.birthday === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(settings.value.birthday)) {
    [year, month, day] = settings.value.birthday.split('-').map(Number)
  } else {
    const d = new Date(settings.value.birthday)
    if (isNaN(d)) return false
    year = d.getFullYear()
    month = d.getMonth() + 1
    day = d.getDate()
  }
  const today = new Date()
  let age = today.getFullYear() - year
  if (
    today.getMonth() + 1 < month ||
    (today.getMonth() + 1 === month && today.getDate() < day)
  ) {
    age--
  }
  return age >= 18 && age <= 125
})
const locationString = computed(() =>
  (!settings.value.location.street && !settings.value.location.postalCode && !settings.value.location.city)
    ? i18n('settings.general.no_address') ?? ''
    : `${settings.value.location.street} ${settings.value.location.postalCode ? settings.value.location.postalCode + ' ' : ''}${settings.value.location.city}`,
)

const rules = {
  firstName: { required, minLength: minLength(2), maxLength: maxLength(40) },
  lastName: { required, minLength: minLength(2), maxLength: maxLength(40) },
}
const v$ = useVuelidate(rules, {
  firstName: computed(() => settings.value.firstName),
  lastName: computed(() => settings.value.lastName),
})

function updateLocalFields (data) {
  if (!data) return

  settings.value = {
    id: data.id,
    firstName: data.firstName,
    lastName: data.lastName,
    aboutMePublic: data.aboutMePublic,
    photo: data.photo,
    gender: data.gender,
    birthday: data.birthday,
    role: data.role,
    mobile: { value: data.mobile, valid: true },
    phone: { value: data.phone, valid: true },
    location: {
      street: data.address?.street,
      postalCode: data.address?.postalCode,
      city: data.address?.city,
    },
    coordinate: { lat: data.coordinate?.lat ?? userStore.lat, lon: data.coordinate?.lon ?? userStore.lon },
    aboutMeInternal: data.aboutMeInternal ?? '',
    position: data.position,
    region: { id: data.regionId, name: data.regionName },
    noAutoDelete: data.noAutoDelete,
  }
}

// Watch for profile data from parent
watch(() => props.profileData, (data) => {
  updateLocalFields(data)
}, { immediate: true })

// Watch for userStore updates (for current user only)
watch(() => userStore.settings, (data) => {
  if (!isMe.value) return
  if (data && data.firstName && props.profileData) {
    updateLocalFields(data)
  }
})

function updateHomeRegion (regionData) {
  settings.value.region.id = regionData.states.id
  settings.value.region.name = regionData.data.text
}
function handleUpdateLocation (data) {
  settings.value.location = data.location
  settings.value.coordinate = data.coordinate
}
function handleValidValue (data) {
  if (data.id === 'mobile') settings.value.mobile = { value: data.value, valid: data.valid }
  if (data.id === 'phone') settings.value.phone = { value: data.value, valid: data.valid }
}
function handleSubmit () {
  const nullableLocation = (!settings.value.location?.street && !settings.value.location?.postalCode && !settings.value.location?.city) ? null : settings.value.location
  const nullableCoordinates = (!settings.value.coordinate?.lat && !settings.value.coordinate?.lon) ? null : settings.value.coordinate
  const formData = {
    id: settings.value.id,
    firstName: settings.value.firstName,
    aboutMePublic: settings.value.aboutMePublic,
    lastName: settings.value.lastName,
    photo: settings.value.photo,
    gender: settings.value.gender,
    birthday: settings.value.birthday,
    mobile: settings.value.mobile.value,
    phone: settings.value.phone.value,
    location: nullableLocation,
    coordinate: nullableCoordinates,
    aboutMeInternal: settings.value.aboutMeInternal,
    role: settings.value.role,
    position: settings.value.position,
    regionId: settings.value.region.id,
    noAutoDelete: settings.value.noAutoDelete,
  }
  if (!isFieldsValid.value) {
    return
  }
  patchUserProfile(settings.value.id, formData).then(() => {
    pulseSuccess(i18n('success') ?? '')
  }).catch((error) => {
    pulseError(i18n('error_unexpected') + ': ' + error)
    console.error(error)
  })
}
</script>

<style>
.invalid-feedback {
  display: block;
  margin-top: 0.25rem;
  font-size: 80%;
  color: #dc3545;
}
</style>

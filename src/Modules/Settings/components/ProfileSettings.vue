<template>
  <b-form v-if="isLoaded" @submit.prevent="handleSubmit">
    <b-alert :show="!userStore.settings.mayChangeVerifiedData">
      <p>
        <i class="fas fa-user-pen mr-1" />
        <span v-text="$t('settings.change_data_info.general')" />
      </p>
      <span v-text="$t('settings.change_data_info.verified')" />
      <Info info-key="change_verified_data" :props="{ link: $url('region_forum', region.id )}" />
    </b-alert>
    <b-alert :show="isAmbassador || isOrgUser">
      <Markdown :source="$t('profile.editNameInfo', {url: $url('editNameInfoUrl')})" />
    </b-alert>
    <div class="row">
      <div class="col-md-6">
        <b-form-group :label="$t('register.login_name')">
          <b-form-input
            id="input-firstname"
            v-model.lazy="v$.firstName.$model"
            :class="{ 'is-invalid': v$.firstName.$error }"
            type="text"
            :disabled="!userStore.settings.mayChangeVerifiedData"
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
            v-model.lazy="v$.lastName.$model"
            :class="{ 'is-invalid': v$.lastName.$error }"
            type="text"
            :disabled="!userStore.settings.mayChangeVerifiedData"
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
          <b-form-select v-model="gender" :options="genderOptions" />
        </b-form-group>
      </div>
      <div class="col-md-6">
        <b-form-group :label="$t('register.geb_datum')">
          <b-form-input
            id="settings-birthdate-input"
            v-model="birthdayFormatted"
            type="date"
            autocomplete="off"
            :disabled="!userStore.settings.mayChangeVerifiedData"
          />
          <div v-if="!isValidBirthdate" class="invalid-feedback">
            {{ $t('register.error_birthdate') }}
          </div>
        </b-form-group>
      </div>

      <div class="col-md-6">
        <b-form-group :label="$t('terminology.mobile_phone')">
          <PhoneNumberInput
            :input-value="mobile"
            input-name="mobile"
            @update-phone-number="handleValidValue"
          />
        </b-form-group>
      </div>
      <div class="col-md-6">
        <b-form-group :label="$t('terminology.landline')">
          <PhoneNumberInput
            :input-value="phone"
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
          <b-input v-model="position" />
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
              v-model="role"
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
              :value="aboutMePublic"
              @update:value="newValue => aboutMePublic = newValue"
            />
          </b-form-group>
        </div>
        <div>
          <b-form-group :label="$t('terminology.homeRegion')">
            <b-input-group>
              <b-form-input
                :value="region.name || $t('search.results.user.no_home_region')"
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
              :value="aboutMeInternal"
              @update:value="newValue => aboutMeInternal = newValue"
            />
          </b-form-group>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <b-form-group :label="$t('no_automatic_delete')">
          <b-form-select v-model="noAutoDelete" :options="noAutoDeleteOptions" />
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
        :initial-value="photo"
      />
    </div>

    <ProfileAddressModal
      ref="AddressModal"
      :coordinate="coordinate"
      :location="location"
      :zoom="zoom"
      @update-location="handleUpdateLocation"
    />
    <RegionTreeModal
      v-if="!isMe"
      ref="homeRegionTree"
      :value="region"
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
import { ref, computed, onMounted, watch } from 'vue'
import ProfilePicture from './ProfilePicture.vue'
import { patchUserProfile, getUserProfileSettings } from '@/api/user'
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

const userStore = useUserStore()

const randomSuffix = Date.now()
const userId = ref(null)
const region = ref({ id: userStore.settings.regionId, name: userStore.settings.regionName })
const position = ref(userStore.settings.position)
const zoom = 17
const firstName = ref(userStore.settings.firstName)
const aboutMePublic = ref(userStore.settings.aboutMePublic)
const lastName = ref(userStore.settings.lastName)
const photo = ref(userStore.settings.photo)
const gender = ref(userStore.settings.gender)
const birthday = ref(userStore.settings.birthday)
const role = ref(userStore.settings.rolle)

const birthdayFormatted = computed({
  get () {
    if (!birthday.value) return ''
    // Convert  ISO 8601 Datetime to YYYY-MM-DD
    return birthday.value.split('T')[0]
  },
  set (value) {
    birthday.value = value
  },
})

const mobile = ref({ value: userStore.settings.mobile, valid: true })
const phone = ref({ value: userStore.settings.phone, valid: true })
const location = ref({
  street: userStore.settings.address?.street,
  postalCode: userStore.settings.address?.postalCode,
  city: userStore.settings.address?.city,
})
const coordinate = ref({ lat: userStore.lat, lon: userStore.lon })
const aboutMeInternal = ref(userStore.settings.aboutMeInternal ?? '')
const noAutoDelete = ref(userStore.settings.noAutoDelete)
const isLoaded = ref(false)

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
const isMe = computed(() => userStore.getUserId === userId.value)
const isAmbassador = computed(() => userStore.isAmbassador)
const isFieldsValid = computed(() =>
  phone.value.valid && mobile.value.valid && !v$.value.$invalid && isValidBirthdate.value,
)
const isValidBirthdate = computed(() => {
  if (!birthday.value) return false
  let year, month, day
  if (typeof birthday.value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(birthday.value)) {
    [year, month, day] = birthday.value.split('-').map(Number)
  } else {
    const d = new Date(birthday.value)
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
  (!location.value.street && !location.value.postalCode && !location.value.city)
    ? i18n('settings.general.no_address') ?? ''
    : `${location.value.street} ${location.value.postalCode ? location.value.postalCode + ' ' : ''}${location.value.city}`,
)

const rules = {
  firstName: { required, minLength: minLength(2), maxLength: maxLength(40) },
  lastName: { required, minLength: minLength(2), maxLength: maxLength(40) },
}
const v$ = useVuelidate(rules, { firstName, lastName })

function getUserIdFromUrl () {
  const match = window.location.pathname.match(/\/user\/(\d+)\/settings/)
  return match ? Number(match[1]) : undefined
}

function updateLocalFields (settings) {
  userId.value = settings.id
  firstName.value = settings.firstName
  lastName.value = settings.lastName
  aboutMePublic.value = settings.aboutMePublic
  photo.value = settings.photo
  gender.value = settings.gender
  birthday.value = settings.birthday
  role.value = settings.rolle
  mobile.value = { value: settings.mobile, valid: true }
  phone.value = { value: settings.phone, valid: true }
  location.value = {
    street: settings.address?.street,
    postalCode: settings.address?.postalCode,
    city: settings.address?.city,
  }
  coordinate.value = { lat: userStore.lat, lon: userStore.lon }
  aboutMeInternal.value = settings.aboutMeInternal ?? ''
  position.value = settings.position
  region.value = { id: settings.regionId, name: settings.regionName }
  noAutoDelete.value = settings.noAutoDelete
  isLoaded.value = true
}

async function loadUserProfile (userId) {
  let settings
  if (userId === undefined || !isMe.value) {
    settings = await getUserProfileSettings(userId)
  } else {
    settings = userStore.settings
  }
  updateLocalFields(settings)
}

onMounted(() => {
  loadUserProfile(getUserIdFromUrl())
})

watch(() => userStore.settings, (settings) => {
  if (settings && settings.firstName) {
    updateLocalFields(settings)
  }
}, { immediate: true })

function updateHomeRegion (regionData) {
  region.value.id = regionData.states.id
  region.value.name = regionData.data.text
}
function handleUpdateLocation (data) {
  location.value = data.location
  coordinate.value = data.coordinate
}
function handleValidValue (data) {
  if (data.id === 'mobile') mobile.value = { value: data.value, valid: data.valid }
  if (data.id === 'phone') phone.value = { value: data.value, valid: data.valid }
}
function handleSubmit () {
  const nullableLocation = (!location.value?.street && !location.value?.postalCode && !location.value?.city) ? null : location.value
  const nullableCoordinates = (!coordinate.value?.lat && !coordinate.value?.lon) ? null : coordinate.value
  const formData = {
    id: userId.value,
    firstName: firstName.value,
    aboutMePublic: aboutMePublic.value,
    lastName: lastName.value,
    photo: photo.value,
    gender: gender.value,
    birthday: birthday.value,
    mobile: mobile.value.value,
    phone: phone.value.value,
    location: nullableLocation,
    coordinate: nullableCoordinates,
    aboutMeInternal: aboutMeInternal.value,
    role: role.value,
    position: position.value,
    regionId: region.value.id,
    noAutoDelete: noAutoDelete.value,
  }
  if (!isFieldsValid.value) {
    return
  }
  patchUserProfile(userId.value, formData).then(() => {
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

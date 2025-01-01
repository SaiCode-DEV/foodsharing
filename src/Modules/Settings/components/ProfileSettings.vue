<template>
  <b-form @submit.prevent="handleSubmit">
    <div
      v-if="!permissions.mayChangeName"
      class="alert alert-light border mb-2"
      @click="$refs.nameInputModal.show()"
    >
      <Markdown :source="$i18n('settings.name_change.desc', {link:'#'})" />
    </div>
    <b-alert :show="isAmbassador || isOrgUser">
      <Markdown :source="$i18n('profile.editNameInfo', {url: $url('editNameInfoUrl')})" />
    </b-alert>
    <div class="row">
      <div class="col-md-6">
        <b-form-group :label="$i18n('register.login_name')">
          <b-form-input
            id="input-firstname"
            v-model.lazy="v$.firstName.$model"
            :class="{ 'is-invalid': v$.firstName.$error }"
            type="text"
            :disabled="!permissions.mayChangeName"
          />
          <div
            v-if="v$.firstName.$error"
            class="invalid-feedback"
          >
            <span v-if="!v$.firstName.required">{{ $i18n('register.firstname_required') }}</span>
            <span v-if="!v$.firstName.minLength">{{ $i18n('register.firstname_minLength') }}</span>
            <span v-if="!v$.firstName.maxLength">{{ $i18n('register.firstname_maxLength') }}</span>
          </div>
        </b-form-group>
      </div>
      <div class="col-md-6">
        <b-form-group :label="$i18n('register.login_surname')">
          <b-form-input
            id="input-lastname"
            v-model.lazy="v$.lastName.$model"
            :class="{ 'is-invalid': v$.lastName.$error }"
            type="text"
            :disabled="!permissions.mayChangeName"
          />
          <div v-if="v$.lastName.$error" class="invalid-feedback">
            <span v-if="!v$.lastName.required">{{ $i18n('register.lastname_required') }}</span>
            <span v-if="!v$.lastName.minLength">{{ $i18n('register.lastname_minLength') }}</span>
            <span v-if="!v$.lastName.maxLength">{{ $i18n('register.lastname_maxLength') }}</span>
          </div>
        </b-form-group>
      </div>

      <div class="col-md-6">
        <b-form-group :label="$i18n('register.select_your_gender')">
          <b-form-select v-model="gender" :options="genderOptions" />
        </b-form-group>
      </div>
      <div class="col-md-6">
        <b-form-group :label="$i18n('register.geb_datum')">
          <b-form-input
            id="settings-birthdate-input"
            v-model="birthday"
            type="date"
            autocomplete="off"
          />
          <div v-if="!isValidBirthdate" class="invalid-feedback">
            {{ $i18n('register.error_birthdate') }}
          </div>
        </b-form-group>
      </div>

      <div class="col-md-6">
        <b-form-group :label="$i18n('terminology.mobile_phone')">
          <PhoneNumberInput
            :input-value="mobile"
            input-name="mobile"
            @update-phone-number="handleValidValue"
          />
        </b-form-group>
      </div>
      <div class="col-md-6">
        <b-form-group :label="$i18n('terminology.landline')">
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
        <b-form-group :label="$i18n('settings.general.current_address')">
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
        <b-form-group v-if="permissions.isOnTeamPage && (isMe || isOrgUser)" :label="$i18n('position')">
          <b-input v-model="position" />
        </b-form-group>
      </div>

      <div class="col-md-6">
        <div v-if="isMe">
          <b-form-group :label="$i18n('terminology.profile_picture')">
            <b-button
              variant="outline-secondary"
              block
              @click="$refs.profilePictureModal.show()"
            >
              {{ $i18n('terminology.profile_picture') }}
            </b-button>
          </b-form-group>
        </div>
        <div v-if="isOrgUser">
          <b-form-group :label="$i18n('foodsaver.manage.role')">
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
        <div v-if="permissions.isOnTeamPage && (isMe || isOrgUser)">
          <b-form-group :label="$i18n('about_me_public')">
            <Markdown :source="$i18n('foodsaver.about_me_public')" />
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
          <b-form-group :label="$i18n('terminology.homeRegion')">
            <b-input-group>
              <b-form-input
                :value="region.name || $i18n('search.results.user.no_home_region')"
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
          <b-form-group :label="$i18n('about_me_intern')">
            <Markdown :source="$i18n('foodsaver.about_me_intern')" />
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
        <b-form-group :label="$i18n('no_automatic_delete')">
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
        {{ $i18n('button.save') }}
      </b-button>
      <ProfilePicture
        ref="profilePictureModal"
        :img-height="400"
        :img-width="400"
        :initial-value="photo"
      />
    </div>

    <NameInputModal ref="nameInputModal" :region-id="region.id" />
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
</template>

<script>
import ProfilePicture from './ProfilePicture.vue'
import { patchUserProfile } from '@/api/user'
import PhoneNumberInput from '@/components/PhoneNumberInput.vue'
import NameInputModal from './NameInputModal.vue'
import ProfileAddressModal from './ProfileAddressModal.vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import Markdown from '@/components/Markdown/Markdown'
import { useUserStore } from '@/stores/user'
import RegionTreeModal from '@/components/regiontree/RegionTreeModal.vue'
import { SELECTABLE_REGION_TYPES } from '@/stores/regions'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength, maxLength } from '@vuelidate/validators'
import { pulseError, pulseSuccess } from '@/script'

const userStore = useUserStore()

export default {
  name: 'ProfileSettings',
  components: {
    MarkdownInput,
    ProfileAddressModal,
    ProfilePicture,
    PhoneNumberInput,
    NameInputModal,
    Markdown,
    RegionTreeModal,
  },
  props: {
    userDetails: { type: Object, default: () => {} },
    permissions: { type: Object, default: () => {} },
  },
  validations: {
    firstName: { required, minLength: minLength(2), maxLength: maxLength(40) },
    lastName: { required, minLength: minLength(2), maxLength: maxLength(40) },
  },
  setup () {
    return {
      userStore,
      v$: useVuelidate(),
    }
  },
  data () {
    return {
      randomSuffix: Date.now(), // to prevent autofill 
      region: { id: this.userDetails.bezirk_id, name: this.userDetails.homeRegionName },
      position: this.userDetails.position,
      zoom: 17,
      userId: this.userDetails.id,
      firstName: this.userDetails.name,
      aboutMePublic: this.userDetails.about_me_public,
      lastName: this.userDetails.nachname,
      photo: this.userDetails.photo,
      gender: this.userDetails.geschlecht,
      birthday: this.userDetails.geb_datum,
      role: this.userDetails.rolle,
      mobile: { value: this.userDetails.mobile, valid: true },
      phone: { value: this.userDetails.phone, valid: true },
      location: { street: this.userDetails.street, postalCode: this.userDetails.postalCode, city: this.userDetails.city },
      coordinate: { lat: this.userDetails.lat, lon: this.userDetails.lon },
      aboutMeInternal: this.userDetails.about_me_intern ?? '',
      noAutoDelete: this.userDetails.no_automatic_delete,
      genderOptions: [
        { value: 1, text: this.$i18n('register.man') },
        { value: 2, text: this.$i18n('register.woman') },
        { value: 3, text: this.$i18n('register.other') },
      ],
      roleOptions: [
        { value: 0, text: this.$i18n('terminology.role.0') },
        { value: 1, text: this.$i18n('terminology.role.1') },
        { value: 2, text: this.$i18n('terminology.role.2') },
        { value: 3, text: this.$i18n('terminology.role.3') },
        { value: 4, text: this.$i18n('terminology.role.4') },
      ],
      noAutoDeleteOptions: [
        { value: 0, text: this.$i18n('automatic_delete') },
        { value: 1, text: this.$i18n('automatic_not_delete') },
      ],
    }
  },
  computed: {
    isFoodSaver () {
      return userStore.isFoodsaver
    },
    selectableRegionTypes () {
      return SELECTABLE_REGION_TYPES
    },
    isOrgUser () {
      return userStore.isOrga
    },
    isMe () {
      return userStore.getUserId === this.userDetails.id
    },
    isAmbassador () {
      return userStore.isAmbassador
    },
    isFieldsValid () {
      return this.phone.valid && this.mobile.valid && !this.v$.$invalid && this.isValidBirthdate
    },
    isValidBirthdate () {
      const date = new Date(this.birthday)
      const age = this.$dateFormatter.getDifferenceToNowInYears(date)
      return age >= 18 && age <= 125 && !!this.birthday
    },
    locationString () {
      return (!this.location.street && !this.location.postalCode && !this.location.city)
        ? this.$i18n('settings.general.no_address')
        : `${this.location.street} ${this.location.postalCode} ${this.location.city}`
    },
  },
  methods: {
    updateHomeRegion (region) {
      this.region.id = region.states.id
      this.region.name = region.data.text
    },
    handleUpdateLocation (data) {
      this.location = data.location
      this.coordinate = data.coordinate
    },
    handleValidValue (data) {
      this[`${data.id}`] = { value: data.value, valid: data.valid }
    },
    handleSubmit () {
      const nullableLocation = (!this.location?.street && !this.location?.postalCode && !this.location?.city) ? null : this.location
      const nullableCoordinates = (!this.coordinate?.lat && !this.coordinate?.lon) ? null : this.coordinate
      const formData = {
        id: this.userId,
        firstName: this.firstName,
        aboutMePublic: this.aboutMePublic,
        lastName: this.lastName,
        photo: this.photo,
        gender: this.gender,
        birthday: this.birthday,
        mobile: this.mobile.value,
        phone: this.phone.value,
        location: nullableLocation,
        coordinate: nullableCoordinates,
        aboutMeInternal: this.aboutMeInternal,
        role: this.role,
        position: this.position,
        regionId: this.region.id,
        noAutoDelete: this.noAutoDelete,
      }
      if (!this.isFieldsValid) {
        return
      }
      patchUserProfile(this.userId, formData).then(() => {
        pulseSuccess(this.$i18n('success'))
      }).catch((error) => {
        pulseError(this.$i18n('error_unexpected') + ': ' + error)
        console.error(error)
      })
    },
  },
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

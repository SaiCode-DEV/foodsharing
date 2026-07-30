<template>
  <b-modal
    id="addBasketModal"
    ref="addBasketModal"
    :title="$t(edit ? 'basket.edit' : 'basket.add')"
    size="lg"
    :cancel-title="$t('globals.close')"
    :ok-title="$t('globals.save')"
    no-close-on-esc
    no-close-on-backdrop
    :ok-disabled="!isDataValid"
    @ok="save"
  >
    <b-alert type="info" show>
      <i class="fas fa-info-circle" />
      {{ $t('basket.public-info') }}
    </b-alert>
    <ImageUpload
      ref="image-upload"
      class="mb-4"
      :gallery-height-in-px="250"
      :previous-images="previousImages"
    />

    <label for="basket-description-input">{{ $t('basket.description') }}:</label>
    <MarkdownInput
      :value.sync="description"
      input-name="basket-description-input"
      class="mb-3"
      :rows="3"
    />

    <label>{{ $t('basket.contact_types') }}:</label>
    <b-form-group
      :invalid-feedback="$t('basket.modal_error.no_contact')"
      :state="contact.chat || contact.phone"
    >
      <b-form-checkbox
        id="chat-checkbox"
        v-model="contact.chat"
        inline
      >
        {{ $t('basket.contact.write') }}
      </b-form-checkbox>
      <b-form-checkbox
        id="phone-checkbox"
        v-model="contact.phone"
        inline
      >
        {{ $t('basket.contact.call') }}
      </b-form-checkbox>
    </b-form-group>
    <b-form-group
      v-if="contact.phone"
      :invalid-feedback="$t('basket.modal_error.no_phone')"
      :state="!!phoneNumber"
    >
      <label for="phone-number-input">{{ $t('globals.telephone_number') }}</label>
      <b-form-input
        id="phone-number-input"
        v-model="phoneNumber"
        type="tel"
        placeholder="+49 ..."
        size="sm"
        inline
      />
    </b-form-group>

    <div v-if="!edit" class="mb-3">
      <label for="duration-select">{{ $t('lifetime') }}</label>
      <b-form-select
        id="duration-select"
        v-model="durationInDays"
        :options="durationOptions"
        size="sm"
      />
    </div>

    <b-form-group
      :label="$t('weight') + ' ' + weights[weightInput].name"
      label-for="weight-range"
    >
      <b-form-input
        id="weight-range"
        v-model="weightInput"
        type="range"
        min="0"
        :max="weights.length - 1"
      />
    </b-form-group>

    <b-form-group
      :label="$t('address') + ':'"
      label-for="use-home-address"
    >
      <b-form-checkbox
        id="use-home-address"
        v-model="useHomeAddress"
        inline
        switch
        :disabled="!hasValidHomeAddress"
      >
        {{ $t('basket.use_home_address') }}
        <span v-if="hasValidHomeAddress">
          ({{ user.address }}, {{ user.postcode }} {{ user.city }})
        </span>
      </b-form-checkbox>
      <LeafletLocationSearch
        v-if="!useHomeAddress"
        id="location-input"
        :zoom="17"
        :coordinates="location"
        :street="address.street"
        :postal-code="address.zipCode"
        :city="address.city"
        :marker-type="MARKER_TYPES.baskets"
        :show-address-fields="false"
        disable-snapping
        @address-change="onAddressChanged"
      />
    </b-form-group>
  </b-modal>
</template>

<script>
import LeafletLocationSearch from '@/components/map/LeafletLocationSearch.vue'
import { useUserStore } from '@/stores/user'
import { addBasket, editBasket } from '@/api/baskets'
import { useBasketStore } from '@/stores/baskets'
import { pulseInfo } from '@/script'
import ImageUpload from '@/components/upload/ImageUpload.vue'
import { MARKER_TYPES } from '@/stores/map'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'

const defaultBasketData = {
  description: '',
  contact: {
    phone: false,
    chat: true,
  },
  phoneNumber: null,
  durationInDays: 3,
  location: { lat: 50.89, lon: 10.13 },
  address: {},
  useHomeAddress: false,
  weightInput: 4,
  previousImages: [],
}

export default {
  components: { LeafletLocationSearch, ImageUpload, MarkdownInput },
  props: {
    basket: { type: Object, default: null },
    edit: { type: Boolean, default: false },
  },
  setup () {
    const userStore = useUserStore()
    const basketStore = useBasketStore()
    return {
      userStore,
      basketStore,
    }
  },
  data () {
    const durationOptions = [1, 2, 3, 5, 7, 14, 21].map(days => ({ value: days, text: this.$t(`basket.valid.${days}`) }))
    const weights = [
      ...[250, 500].map(weightInGrams => ({ name: `${weightInGrams} g`, weightInGrams })),
      ...[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 30, 40, 50, 75, 100].map(weightInKg => ({ name: `${weightInKg} kg`, weightInGrams: weightInKg * 1000 })),
    ]
    if (!this.edit) {
      // Init with user details will run asynchronously in the background
      this.initUsingUserDetails()
      // Return default values for the form while user details are being fetched
      return Object.assign({}, { durationOptions, weights }, defaultBasketData)
    }
    this.initUsingExistingBasket()
    return {
      durationOptions,
      weights,
      description: this.basket.description,
      contact: {
        chat: this.basket.contactTypes.includes(1),
        phone: this.basket.contactTypes.includes(2),
      },
      phoneNumber: this.basket.mobile || this.basket.telephone,
      durationInDays: undefined,
      location: this.basket.location,
      address: {},
      useHomeAddress: this.useHomeAddress,
      weightInput: Math.max(0, weights.findIndex(weight => weight.weightInGrams === this.basket.weightInGrams)),
      previousImages: this.basket.pictures,
    }
  },
  computed: {
    MARKER_TYPES: () => MARKER_TYPES,
    user () {
      return this.userStore.getUserDetails
    },
    isDataValid () {
      return this.description.trim() && (this.contact.chat || this.contact.phone) && (this.contact.phone ? this.phoneNumber : true)
    },
    hasValidHomeAddress () {
      return Boolean(this.user?.coordinates?.lat && this.user.address && this.user.city)
    },
  },
  methods: {
    onAddressChanged (coordinates, street, postalCode, city) {
      this.location = coordinates
      this.address.street = street
      this.address.zipCode = postalCode
      this.address.city = city
    },
    async initUsingUserDetails () {
      await this.userStore.fetchDetails()

      if (!this.user) {
        this.phoneNumber = ''
        this.useHomeAddress = false
        return
      }

      this.phoneNumber = this.user.mobile || this.user.landline || ''

      if (this.user.coordinates && (this.user.address || this.user.postcode)) {
        this.useHomeAddress = true
        this.location = Object.assign({}, this.user.coordinates || {})
        this.address = {
          street: this.user.address || '',
          zipCode: this.user.postcode || '',
          city: this.user.city || this.address.city || '',
        }
      }
    },

    async initUsingExistingBasket () {
      await this.userStore.fetchDetails()

      if (!this.user || !this.user.coordinates) return

      if (!this.phoneNumber) {
        this.phoneNumber = this.user.mobile || this.user.landline || ''
      }

      const maxDifferenceFromHomeLocation = Math.max(
        Math.abs(this.basket.location.lat - this.user.coordinates.lat),
        Math.abs(this.basket.location.lon - this.user.coordinates.lon),
      )
      this.useHomeAddress = this.hasValidHomeAddress && maxDifferenceFromHomeLocation < 1e-5

      if (this.useHomeAddress) {
        this.address = {
          street: this.user.address || '',
          zipCode: this.user.postcode || '',
          city: this.user.city || this.address.city || '',
        }
      }
    },
    async getBasketData () {
      const pictures = (await this.$refs['image-upload'].uploadImages() || []).filter(Boolean)
      const location = Object.assign({}, this.useHomeAddress ? this.user.coordinates : this.location)

      return {
        description: this.description,
        pictures,
        contactTypes: [...(this.contact.chat ? [1] : []), ...(this.contact.phone ? [2] : [])],
        mobile: this.phoneNumber,
        lifeTimeInDays: this.durationInDays,
        lat: location.lat,
        lon: location.lon,
        weightInGrams: this.weights[this.weightInput].weightInGrams,
      }
    },
    async addBasket () {
      await addBasket(await this.getBasketData())
      pulseInfo(this.$t('basket.published'))
      this.resetModal()
      await this.basketStore.fetchOwn(true)
    },
    async editBasket () {
      await editBasket(this.basket.id, await this.getBasketData())
      await this.basketStore.fetchOwn(true)
      location.reload() // as long as part of the basket page is written in php, the new basket data only is used in the page upon reload.
    },
    resetModal () {
      Object.assign(this, defaultBasketData)
      this.initUsingUserDetails()
    },
    save () {
      if (this.edit) {
        this.editBasket()
      } else {
        this.addBasket()
      }
    },
  },
}
</script>

<style scoped lang="scss">
#addBasketModal #location-input{
  padding: 0.75em 0.75em 0.25em 0.75em;
  box-shadow: 0 0 4px 2px #0002 inset;
  border-radius: var(--border-radius);
  margin-top: 0.5em;
}
</style>

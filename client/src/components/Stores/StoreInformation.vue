<template>
  <b-card no-body>
    <b-tabs
      content-class="mt-3"
      card
      fill
    >
      <b-tab
        :title="$i18n('storeview.common')"
        active
      >
        <b-card-text>
          <b-form-group
            :label="$i18n('name')"
            label-for="storeName"
            class="bootstrap input-wrapper"
          >
            <b-form-input
              id="storeName"
              v-model="store.name"
              :disabled="!editMode"
            />
          </b-form-group>
          <PublicInfo
            :public-info="store.publicInfo"
            :disabled="!editMode"
            @update:public-info="updatePublicInfo"
          />
        </b-card-text>
      </b-tab>
      <b-tab
        :title="$i18n('storeview.source_data')"
        @click="dispatchResize"
      >
        <b-card-text>
          <b-form-group
            v-if="!store.contact"
            :label="$i18n('ansprechpartner')"
            class="bootstrap input-wrapper"
          >
            <small>
              {{ $i18n('storeview.no_permission_to_view') }}
            </small>
          </b-form-group>
          <b-form-group
            v-if="store.contact"
            :label="$i18n('ansprechpartner')"
            class="bootstrap input-wrapper"
          >
            <b-form-group
              :label="$i18n('storeview.contact.name')"
              label-for="contactName"
            >
              <b-form-input
                id="contactName"
                v-model="store.contact.name"
                :disabled="!editMode"
              />
            </b-form-group>
            <b-form-group
              :label="$i18n('terminology.phone')"
              label-for="phone"
            >
              <b-form-input
                id="phone"
                v-model="store.contact.phone"
                :disabled="!editMode"
              />
            </b-form-group>
            <b-form-group
              :label="$i18n('fax')"
              label-for="fax"
            >
              <b-form-input
                id="fax"
                v-model="store.contact.fax"
                :disabled="!editMode"
              />
            </b-form-group>
            <b-form-group
              :label="$i18n('email')"
              label-for="email"
            >
              <b-form-input
                id="email"
                v-model="store.contact.email"
                :type="'email'"
                :disabled="!editMode"
              />
            </b-form-group>
          </b-form-group>
          <ChainSearchPicker
            ref="modal_open_chain_search_picker"
            :store-chains="storeChains"
            @store-chain-selected="selectStoreChainFromSearchPicker"
          />
          <b-form-group
            :label="$i18n('kette_id')"
            label-for="chainId"
            class="bootstrap input-wrapper"
          >
            {{ getChainTextById }}
            <b-button
              variant="primary"
              class="ml-4"
              :disabled="!editMode"
              @click="openChainSearchPicker"
            >
              {{ $i18n('storeview.choose_chain') }}
            </b-button>
          </b-form-group>
          <b-form-group
            :label="$i18n('address')"
            label-for="location"
          >
            <LeafletLocationSearch
              id="location"
              :zoom="17"
              :coordinates="store.location"
              :street="store.address.street"
              :postal-code="store.address.zipCode"
              :city="store.address.city"
              :disabled="!editMode"
              :marker-type="MARKER_TYPES.stores"
              allow-address-correction
              @address-change="onAddressChanged"
            />
          </b-form-group>
        </b-card-text>
      </b-tab>
      <b-tab
        :title="$i18n('storeview.management')"
      >
        <b-card-text>
          <b-form-group
            :label="$i18n('bezirk')"
            label-for="region"
          >
            <b-input-group>
              <b-form-input
                :value="store.region.name"
                type="text"
                :disabled="true"
              />
              <b-input-group-append>
                <b-button
                  variant="outline-secondary"
                  @click="$refs.storeRegionTree.openModal()"
                >
                  <i class="far fa-edit" />
                </b-button>
              </b-input-group-append>
            </b-input-group>
          </b-form-group>
          <b-form-group
            :label="$i18n('betrieb_kategorie_id')"
            label-for="categoryId"
            class="bootstrap input-wrapper"
          >
            <b-form-select
              id="categoryId"
              v-model="store.categoryId"
              :options="categoryTypes"
              :disabled="!editMode"
            />
          </b-form-group>
          <b-form-group
            :label="$i18n('storeview.cooperation_start')"
            label-for="cooperationStart"
            class="bootstrap input-wrapper"
          >
            <b-form-datepicker
              id="cooperationStart"
              v-model="store.cooperationStart"
              :disabled="!editMode"
            />
          </b-form-group>
          <b-form-group
            :label="$i18n('storeview.cooperation_status')"
            label-for="cooperationStatus"
          >
            <b-form-select
              id="cooperationStatus"
              v-model="store.cooperationStatus"
              :options="storeCooperationStatusTypes"
              :disabled="!editMode"
            />
          </b-form-group>
        </b-card-text>
      </b-tab>
      <b-tab
        :title="$i18n('terminology.pickup')"
      >
        <b-card-text>
          <b-form-group
            :label="$i18n('store.average_collection_quantity')"
            label-for="weight"
            class="bootstrap input-wrapper"
          >
            <b-form-select
              id="weight"
              v-model="store.weight"
              :options="weightTypes"
              :disabled="!editMode"
            />
          </b-form-group>
          <b-form-group
            :label="$i18n('public_time')"
            label-for="publicTime"
            class="bootstrap input-wrapper"
          >
            <b-form-select
              id="publicTime"
              v-model="store.publicTime"
              :options="publicTimes"
              :disabled="!editMode"
            />
          </b-form-group>
          <b-form-group
            :label="$i18n('prefetchtime')"
            label-for="calendarInterval"
            class="bootstrap input-wrapper"
          >
            <b-form-spinbutton
              id="calendarInterval"
              v-model="calendarInterval"
              min="0"
              max="8"
              class="w-50"
              :disabled="!editMode"
              :formatter-fn="calendarIntervalFormatter"
              wrap
            />
          </b-form-group>
          <b-form-group
            :label="$i18n('use_region_pickup_rule')"
            label-for="useRegionPickupRule"
            class="input-wrapper"
          >
            <b-form-checkbox
              id="useRegionPickupRule"
              v-model="store.options.useRegionPickupRule"
              switch
              :disabled-field="!editMode"
              :disabled="!editMode"
            />
          </b-form-group>
          <RegularPickup
            :edit-pickups.sync="editPickups"
            :loaded-pickups="loadedPickups"
            :edit-mode="editMode"
            :max-count-pickup-slot="maxCountPickupSlot"
          />
        </b-card-text>
      </b-tab>
      <b-tab
        :title="$i18n('storeview.team')"
      >
        <b-card-text>
          <b-form-group :label="$i18n('storeedit.fetch.teamStatus')">
            <b-form-select
              id="teamStatus"
              v-model="store.teamStatus"
              :options="teamStatusOptions"
              :disabled="!editMode"
            />
          </b-form-group>

          <b-form-group v-if="showHygieneSetting">
            <template #label>
              {{ $i18n('storeedit.fetch.hygieneRequirement') }}
              <Info info-key="hygieneRequirement" />
            </template>
            <b-form-checkbox v-model="store.isHygieneRequired" :disabled="!editMode">
              {{ $i18n('storeedit.fetch.hygieneRequired') }}
            </b-form-checkbox>
          </b-form-group>
          <b-form-group
            id="fieldset-2"
            :description="$i18n('storeview.visible_for_team')"
            :label="$i18n('storeview.specials')"
            label-for="description"
          >
            <MarkdownInput
              id="description"
              :rows="5"
              :value="store.description"
              :disabled="!editMode"
              :region-id="store.region.id"
              @update:value="newDescription => store.description = newDescription"
            />
          </b-form-group>
        </b-card-text>
      </b-tab>
      <b-tab
        :title="$i18n('storeview.public_and_statistics')"
      >
        <b-card-text>
          <b-form-group
            :label="$i18n('ueberzeugungsarbeit')"
            label-for="effort"
            class="bootstrap input-wrapper"
          >
            <b-form-select
              v-if="store.effort !== null"
              id="effort"
              v-model="store.effort"
              :options="convinceStatusTypes"
              :disabled="!editMode"
            />
            <small
              v-if="store.effort === null"
            >
              {{ $i18n('storeview.no_permission_to_view') }}
            </small>
          </b-form-group>

          <b-form-group
            :label="$i18n('sticker')"
            label-for="showsSticker"
            class="bootstrap input-wrapper"
          >
            <b-form-select
              id="showsSticker"
              v-model="store.showsSticker"
              :options="publicityAndStickerOptions"
              :disabled="!editMode"
            />
          </b-form-group>
          <b-form-group
            :label="$i18n('presse')"
            label-for="publicity"
            class="bootstrap input-wrapper"
          >
            <b-form-select
              id="publicity"
              v-model="store.publicity"
              :options="publicityAndStickerOptions"
              :disabled="!editMode"
            />
          </b-form-group>

          <b-form-group
            :label="$i18n('storeview.groceries.label')"
            label-for="tags-with-dropdown"
          >
            <small
              v-if="store.groceries === null"
            >
              {{ $i18n('storeview.no_permission_to_view') }}
            </small>
            <b-form-tags
              v-if="store.groceries !== null"
              id="tags-with-dropdown"
              v-model="storeFoodNames"
              :disabled="!editMode"
              n-outer-focus
              class="mb-2"
            >
              <template #default="{ tags, disabled, addTag, removeTag }">
                <ul
                  v-if="tags.length > 0"
                  class="list-inline d-inline-block mb-2"
                >
                  <li
                    v-for="tag in tags"
                    :key="tag"
                    class="list-inline-item"
                  >
                    <b-form-tag
                      :title="tag"
                      :disabled="disabled"
                      variant="info"
                      @remove="removeTag(tag)"
                    >
                      {{ tag }}
                    </b-form-tag>
                  </li>
                </ul>

                <b-dropdown
                  v-if="editMode"
                  size="sm"
                  variant="outline-secondary"
                  block
                  menu-class="w-100"
                >
                  <template #button-content>
                    <i class="fas fa-cutlery" /> {{ $i18n('storeview.groceries.select_tag') }}
                  </template>
                  <b-dropdown-form @submit.stop.prevent="() => {}">
                    <b-form-group
                      :label="$i18n('storeview.groceries.search_tag')"
                      label-for="tag-search-input"
                      label-cols-md="auto"
                      class="mb-0"
                      label-size="sm"
                      :description="foodSearchCriteriaFieldDesc"
                      :disabled="disabled"
                    >
                      <b-form-input
                        id="tag-search-input"
                        v-model="foodSearchCriteriaField"
                        type="search"
                        size="sm"
                        autocomplete="off"
                      />
                    </b-form-group>
                  </b-dropdown-form>
                  <b-dropdown-divider />
                  <b-dropdown-item-button
                    v-for="option in availableFoodOptions"
                    :key="option"
                    @click="onSelectFoodClick({ option, addTag })"
                  >
                    {{ option }}
                  </b-dropdown-item-button>
                  <b-dropdown-text v-if="availableFoodOptions.length === 0">
                    {{ $i18n('storeview.groceries.no_tag_preset') }}
                  </b-dropdown-text>
                </b-dropdown>
              </template>
            </b-form-tags>
          </b-form-group>
        </b-card-text>
      </b-tab>
    </b-tabs>
    <b-button
      v-if="mayEditStore"
      variant="primary"
      :disabled="!publicInfoState"
      @click="submit"
    >
      {{ $i18n('button.save') }}
    </b-button>
    <region-tree-modal
      v-if="store.region"
      ref="storeRegionTree"
      :value="store.region"
      modal-title="storeview.select_related_region"
      input-name="regionId"
      :selectable-region-types="[REGION_UNIT_TYPE.CITY, REGION_UNIT_TYPE.BIG_CITY, REGION_UNIT_TYPE.PART_OF_TOWN]"
      :disabled="!editMode"
      @input="updateRegion"
    />
  </b-card>
</template>

<script>
// Stores
import StoreData, { STORE_PUBLICITY_AND_STICKER_OPTIONS } from '@/stores/stores'
import PickupsData from '@/stores/pickups'

// Others
import { pulseError, showLoader, hideLoader, pulseSuccess } from '@/script'
import { updateStore } from '@/api/stores'
import { editRegularPickup } from '@/api/pickups'

import LeafletLocationSearch from '@/components/map/LeafletLocationSearch.vue'
import RegionTreeModal from '@/components/regiontree/RegionTreeModal.vue'
import RegularPickup from '@/components/Stores/RegularPickup.vue'

import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import AutoResizeTextareaMixin from '@/mixins/AutoResizeTextareaMixin'
import { REGION_UNIT_TYPE } from '@/stores/regions'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import ChainSearchPicker from '@/components/Stores/ChainSearchPicker.vue'
import PublicInfo from '@/components/Stores/PublicInfo.vue'
import Info from '../Help/Info.vue'
import { MARKER_TYPES } from '@/stores/map'

export default {
  name: 'StoreInformationEditModal',
  components: {
    LeafletLocationSearch,
    RegionTreeModal,
    RegularPickup,
    MarkdownInput,
    ChainSearchPicker,
    PublicInfo,
    Info,
  },
  mixins: [MediaQueryMixin, AutoResizeTextareaMixin],
  props: {
    isJumper: { type: Boolean, default: null },
    storeId: { type: Number, default: null },
    mayEditStore: { type: Boolean, default: null },
    isCoordinator: { type: Boolean, default: null },
    isVerified: { type: Boolean, default: null },
    loadedPickups: { type: Array, default: () => [] },
  },
  data () {
    return {
      editMode: false,
      editPickups: [],
      selectedWeekDay: null,
      foodSearchCriteriaField: '',
      storeFoodNames: [],
      teamStatusOptions: [
        { value: 0, text: this.$i18n('store.team.is_closed') },
        { value: 1, text: this.$i18n('menu.entry.helpwanted') },
        { value: 2, text: this.$i18n('menu.entry.helpneeded') },
      ],
      publicityAndStickerOptions: [
        { value: STORE_PUBLICITY_AND_STICKER_OPTIONS.NO, text: this.$i18n('storeview.publicity_and_sticker_options.no') },
        { value: STORE_PUBLICITY_AND_STICKER_OPTIONS.YES, text: this.$i18n('storeview.publicity_and_sticker_options.yes') },
        { value: STORE_PUBLICITY_AND_STICKER_OPTIONS.NOT_CHOSEN, text: this.$i18n('storeview.publicity_and_sticker_options.not_yet_clarified') },
      ],
      store: {},
      chainSearchCriteriaField: '',
      publicInfoState: true,
      showHygieneSetting: false,
    }
  },
  computed: {
    MARKER_TYPES: () => MARKER_TYPES,
    getChainTextById () {
      if (this.storeChains?.length > 0 && this.store.chainId != null) {
        const chain = this.storeChains.find(chain => chain.value === this.store.chainId)
        return chain ? chain.text : ''
      }
      return this.$i18n('store.no_chain_choosen')
    },
    REGION_UNIT_TYPE () {
      return REGION_UNIT_TYPE
    },
    storeInformation () {
      return StoreData.getters.getStoreInformation()
    },
    calendarInterval: {
      get () {
        return this.store.calendarInterval / 3600 / 24 / 7
      },
      set (val) {
        this.store.calendarInterval = val * 3600 * 24 * 7
      },
    },
    maxCountPickupSlot () {
      return StoreData.getters.getMaxCountPickupSlot()
    },
    storeChains () {
      return StoreData.getters.getStoreChains()?.map(item => ({ value: item.id, text: item.name }))
    },
    storeCooperationStatusTypes () {
      return StoreData.getters.getStoreCooperationStatus()?.map(item => ({ value: item.id, text: item.name }))
    },
    weightTypes () {
      return StoreData.getters.getStoreWeightTypes()?.map(item => ({ value: item.id, text: item.name }))
    },
    publicTimes () {
      return StoreData.getters.getPublicTimes()?.map(item => ({ value: item.id, text: item.name }))
    },
    categoryTypes () {
      return StoreData.getters.getStoreCategoryTypes()?.map(item => ({ value: item.id, text: item.name }))
    },
    convinceStatusTypes () {
      return StoreData.getters.getStoreConvinceStatusTypes()?.map(item => ({ value: item.id, text: item.name }))
    },
    foodSearchCriteria () {
      return this.foodSearchCriteriaField.trim().toLowerCase()
    },
    storeFoodIds () {
      const selectedValues = StoreData.getters.getGrocerieTypes().filter(opt => this.storeFoodNames.indexOf(opt.name) !== -1).filter(opt => opt.name)
      return [...new Set(selectedValues.map(opt => opt.id))]
    },
    availableFoodOptions () {
      const foodSearchCriteria = this.foodSearchCriteria
      // Filter out already selected options
      const options = StoreData.getters.getGrocerieTypes().filter(opt => this.storeFoodNames.indexOf(opt.name) === -1)
      if (foodSearchCriteria) {
        // Show only options that match foodSearchCriteria
        return options.filter(opt => opt.name.toLowerCase().indexOf(foodSearchCriteria) > -1).map(opt => opt.name)
      }
      // Show all available options
      return [...new Set(options.map(opt => opt.name))]
    },
    foodSearchCriteriaFieldDesc () {
      if (this.foodSearchCriteria && this.availableFoodOptions.length === 0) {
        return this.$i18n('storeview.groceries.no_match')
      }
      return ''
    },
  },
  async created () {
    // Load data
    this.store = this.storeInformation
    this.editMode = (this.mayEditStore || this.isCoordinator)
    this.editPickups = this.simpleClone(this.loadedPickups)

    if (this.store.categoryId === null) {
      this.store.categoryId = 0
    }

    if (this.store.groceries !== null) {
      const selectedValues = StoreData.getters.getGrocerieTypes().filter(opt => this.store.groceries.indexOf(opt.id) !== -1).map(opt => opt.name)
      this.storeFoodNames = [...new Set(selectedValues)]
    } else {
      this.storeFoodNames = []
    }
  },
  async mounted () {
    this.showHygieneSetting = await this.$isFeatureToggleActive('hygieneQuiz')
  },
  methods: {
    updateRegion (region) {
      this.store.region.id = region.states.id
      this.store.region.name = region.data.text
    },
    updatePublicInfo ({ publicInfo, publicInfoState }) {
      this.store.publicInfo = publicInfo
      this.publicInfoState = publicInfoState
    },
    simpleClone (value) {
      return JSON.parse(JSON.stringify(value))
    },
    selectStoreChainFromSearchPicker (selectedChain) {
      this.store.chainId = selectedChain.value
    },
    dispatchResize () {
      window.dispatchEvent(new Event('resize'))
    },
    async submit () {
      if (!this.publicInfoState) {
        pulseError(this.$i18n('storeview.invalid_field'))
        return
      }

      try {
        showLoader()
        const store = this.simpleClone(this.store)
        store.regionId = this.store.region.id
        delete store.region
        store.groceries = this.storeFoodIds
        await updateStore(store)
        if (JSON.stringify(this.loadedPickups) !== JSON.stringify(this.editPickups)) {
          await editRegularPickup(this.storeId, this.editPickups)
          await PickupsData.mutations.fetchRegularPickup(this.storeId)
        }
        pulseSuccess(this.$i18n('globals.saved'))
        this.$bvModal.hide('storeInformationModal')
      } catch (err) {
        const errorDescription = err.jsonContent ?? { message: '' }
        const errorMessage = `(${errorDescription.message ?? 'Unknown'})`
        pulseError(this.$i18n('storeedit.unsuccess', { error: errorMessage }))
      } finally {
        hideLoader()
      }
    },
    async resetModal () {
      await StoreData.mutations.loadStoreInformation(this.storeId)
      if (this.store.groceries !== null) {
        const selectedValues = StoreData.getters.getGrocerieTypes().filter(opt => this.store.groceries.indexOf(opt.id) !== -1).map(opt => opt.name)
        this.storeFoodNames = [...new Set(selectedValues)]
      } else {
        this.storeFoodNames = []
      }
    },
    calendarIntervalFormatter (value) {
      if (value === 0) {
        return this.$i18n('storeview.calendar_interval.not_set')
      } else if (value === 1) {
        return this.$i18n('storeview.calendar_interval.single')
      } else {
        return this.$i18n('storeview.calendar_interval.multiply', { weeks: value })
      }
    },
    onSelectFoodClick ({ option, addTag }) {
      addTag(option)
      this.foodSearchCriteriaField = ''
    },
    onAddressChanged (coordinates, street, postalCode, city) {
      this.store.location = coordinates
      this.store.address.street = street
      this.store.address.zipCode = postalCode
      this.store.address.city = city
    },
    openChainSearchPicker () {
      this.$refs.modal_open_chain_search_picker.show()
    },
  },
}
</script>

<style lang="scss" scoped>
.selector select {
    margin-bottom: 0.25rem;
}

.test-modal .modal-dialog {
  max-width: 100%;
  margin: 0;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  height: 100vh;
  display: flex;
  position: fixed;
  z-index: 100000;
}
</style>

<style>
.b-form-btn-label-control.form-control > .btn {
  font-size: 0.5em;
}

ul.dropdown-menu.w-100.show {
  max-height: 21rem;
  overflow-x: auto;
}
</style>

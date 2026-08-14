<template>
  <div class="card mb-3 rounded">
    <div class="card-header text-white bg-primary">
      <slot name="head-title" />
      <span>
        {{ $t('filterlist.some_in_all', {some: storesFiltered.length, all: stores.length}) }}
      </span>
    </div>
    <div v-if="stores.length" class="card-body p-0">
      <ConfigureableList
        :fields.sync="fields"
        :selection.sync="fieldSelection"
        :default-fields="defaultFieldsOrder"
        :state.sync="state"
        :storage-key="configStoreKey"
      >
        <template #head="{ showConfigurationDialog }">
          <div class="form-row p-1 ">
            <div class="d-flex align-items-center col-2">
              <label class=" col-form-label col-form-label-sm">
                {{ $t('store.filter') }}
              </label>
            </div>
            <div class="d-flex align-items-center col-4">
              <label class="mb-0">
                <input
                  v-model.trim="state.filterText"
                  type="text"
                  class="form-control form-control-sm"
                  :placeholder="$t('storelist.filter_placeholder')"
                >
              </label>
            </div>
            <div class="d-flex align-items-center col-3">
              <b-form-select
                v-model="state.filterStatus"
                :options="statusOptions"
                size="mb"
              />
            </div>
            <div class="d-flex align-items-center col">
              <button
                v-b-tooltip.hover
                type="button"
                class="btn btn-sm"
                :title="$t('storelist.emptyfilters')"
                @click="clearFilter"
              >
                <i class="fas fa-times" />
              </button>
            </div>
            <slot name="header-actions" />
            <button
              type="button"
              class="btn btn-sm ml-auto shadow-none"
              @click="showConfigurationDialog"
            >
              <i class="fas fa-gear" />
            </button>
          </div>
        </template>
        <template #default>
          <b-table-mobile-friendly
            id="store-list"
            :fields="selectedFields"
            :current-page="state.currentPage"
            :per-page="perPage"
            :sort-by.sync="state.sortBy"
            :sort-desc.sync="state.sortDesc"
            :items="storesFiltered"
            small
            hover
            responsive
            @content-overflow="isStoreListOverflowing = $event"
          >
            <template
              #cell(cooperationStatus)="row"
            >
              <div class="text-center">
                <StoreStatusIcon :cooperation-status="row.value" />
              </div>
            </template>
            <template #cell(name)="row">
              <CategoryIcon :entry="row.item" />
              <router-link
                :to="$url('store', row.item.id)"
                class="ui-corner-all"
              >
                {{ row.value }}
              </router-link>
            </template>
            <template #cell(region)="row">
              {{ row.value.name }}
            </template>
            <template #cell(actions)="row">
              <div class="d-flex">
                <b-button
                  :to="mapLink(row.item)"
                  class="mr-2"
                  :title="$t('storelist.map')"
                  size="sm"
                >
                  <i class="fas fa-map-marker-alt" />
                </b-button>
                <NavigateWithSelector
                  :latitude="row.item.location.lat"
                  :longitude="row.item.location.lon"
                  small
                />
              </div>
            </template>
          </b-table-mobile-friendly>
        </template>
      </ConfigureableList>
      <div class="float-right p-1 pr-3">
        <b-pagination
          v-model="state.currentPage"
          :total-rows="storesFiltered.length"
          :per-page="perPage"
          aria-controls="store-list"
          class="my-0"
        />
      </div>
    </div>
    <div
      v-else
      class="card-body d-flex justify-content-center"
    >
      {{ $t('store.noStores') }}
      <slot name="no-stores-footer-actions" />
    </div>
  </div>
</template>

<script>
import {
  BFormSelect,
  VBTooltip,
} from 'bootstrap-vue'
import StoreStatusIcon from './StoreStatusIcon.vue'
import storeEntryMixin from '@/mixins/storeEntryMixin'
import ConfigureableList from '@/components/ConfigureableList.vue'
import BTableMobileFriendly from '@/components/BTableMobileFriendly.vue'
import { useStoreStore } from '@/stores/store'
import { useUserStore } from '@/stores/user'
import NavigateWithSelector from '@/components/UI/NavigateWithSelector.vue'
import { PROFILE_STORE_TEAM_STATE } from '@/stores/profiles'

const storeStore = useStoreStore()
const userStore = useUserStore()

export default {
  components: {
    BTableMobileFriendly,
    BFormSelect,
    StoreStatusIcon,
    ConfigureableList,
    NavigateWithSelector,
    CategoryIcon: {
      mixins: [storeEntryMixin],
      props: { entry: { type: Object, required: true } },
      render (h) {
        return h('i', {
          class: ['fas', 'fa-fw', this.storeCategoryTypeIcon, 'text-muted', 'mr-1'],
          directives: [
            { name: 'b-tooltip', value: this.pickupStringStatus, modifiers: { hover: true } },
          ],
          style: { cursor: 'help' },
        })
      },
    },
  },
  directives: { VBTooltip },
  props: {
    stores: { type: Array, required: true },
    showMemberState: { type: Boolean, default: true },
    configStoreKey: { type: String, default: undefined },
  },
  data () {
    return {
      perPage: 20,
      state: {
        sortBy: 'createdAt',
        sortDesc: true,
        currentPage: 1,
        filterText: '',
        filterStatus: null,
      },
      statusOptions: [
        { value: null, text: this.$t('storestatus.placeholder') },
        { value: 1, text: this.$t('storestatus.1') }, // CooperationStatus::NO_CONTACT
        { value: 2, text: this.$t('storestatus.2') }, // CooperationStatus::IN_NEGOTIATION
        { value: 4, text: this.$t('storestatus.4') }, // CooperationStatus::DOES_NOT_WANT_TO_WORK_WITH_US
        { value: 5, text: this.$t('storestatus.5') }, // CooperationStatus::COOPERATION_ESTABLISHED
        { value: 6, text: this.$t('storestatus.6') }, // CooperationStatus::GIVES_TO_OTHER_CHARITY
        { value: 7, text: this.$t('storestatus.7') }, // CooperationStatus::PERMANENTLY_CLOSED
      ],
      fieldsDefinition: [
        {
          key: 'cooperationStatus',
          label: this.$t('storelist.status'),
          tdClass: 'status',
          sortable: true,
        },
        {
          key: 'name',
          label: this.$t('storelist.name'),
          sortable: true,
        },
        {
          key: 'street',
          label: this.$t('storelist.address'),
          sortable: true,
        },
        {
          key: 'zipCode',
          label: this.$t('storelist.zipcode'),
          sortable: true,
        },
        {
          key: 'city',
          label: this.$t('storelist.city'),
          sortable: true,
        },

        {
          key: 'createdAt',
          label: this.$t('storelist.added'),
          tdClass: 'status',
          sortable: true,
        },
        {
          key: 'region',
          label: this.$t('storelist.region'),
          sortable: true,
        },
        {
          key: 'memberState',
          label: this.$t('storelist.memberState'),
          tdClass: 'status',
          sortable: true,
          formatter: (value, key, item) => this.getUserRole(item.id),
          sortByFormatted: true,
          filterByFormatted: true,
        },
        {
          key: 'actions',
          label: this.$t('storelist.actions'),
          sortable: false,
        },
      ],
      availableFields: [],
      fieldSelection: [],
      isStoreListOverflowing: false,
    }
  },
  computed: {
    fields: {
      get () {
        return this.availableFields.map(fieldKey => this.fieldsDefinition.find(field => field.key === fieldKey))
      },
      set (fields) {
        this.availableFields = fields
      },
    },
    selectedFields () {
      return this.fields.filter(field => this.fieldSelection.includes(field.key))
    },
    storesFiltered () {
      let stores = this.stores
      if (this.state.filterStatus) {
        stores = stores.filter(store => store.cooperationStatus === this.state.filterStatus)
      }
      if (this.state.filterText) {
        // match filterText an all store properties
        stores = stores.filter(store => {
          for (const prop in store) {
            const propValue = store[prop]
            if (typeof propValue === 'string' && propValue.toLocaleLowerCase().indexOf(this.filterTextLower) !== -1) {
              return true
            }
          }
          return false
        })
      }
      return stores
    },
    filterTextLower () {
      return this.state.filterText.toLowerCase()
    },
    defaultFieldsOrder () {
      const fieldOrder = ['cooperationStatus', 'name', 'street', 'zipCode', 'city', 'createdAt', 'region', 'memberState', 'actions']
      if (this.isStoreListOverflowing) {
        [fieldOrder[0], fieldOrder[1]] = [fieldOrder[1], fieldOrder[0]] // swap cooperationStatus & name
      }
      return fieldOrder
    },
    userId () {
      return userStore.getUserId
    },
  },
  created () {
    this.availableFields = this.fieldsDefinition.map(field => field.key)
    this.fieldSelection = this.showMemberState ? this.availableFields : ['cooperationStatus', 'name', 'street', 'zipCode', 'city', 'createdAt', 'region', 'actions']
  },
  methods: {
    getUserRole (storeId) {
      if (storeStore.userRelations === null) {
        storeStore.fetchUserStoreRelations(this.userId)
        return '...loading'
      } else {
        const relation = storeStore.userRelations.find(relation => relation.id === storeId)
        if (relation) {
          if (relation.isManaging) {
            return this.$t('store.managing')
          }
          switch (relation.membershipStatus) {
            case PROFILE_STORE_TEAM_STATE.REQUESTED: return this.$t('store.isAppliedForTeam')
            case PROFILE_STORE_TEAM_STATE.ACTIVE: return this.$t('store.member')
            case PROFILE_STORE_TEAM_STATE.JUMPER: return this.$t('store.jumping')
            case PROFILE_STORE_TEAM_STATE.INVITED: return this.$t('store.invited')
          }
        }
      }
      // not a member
    },
    fetchData () {
      storeStore.fetchStoresForUser(this.userId)
    },
    clearFilter () {
      this.state.filterStatus = null
      this.state.filterText = ''
    },
    mapLink (store) {
      return this.$url('map', { storeId: store.id })
    },
  },
}
</script>
<style>
  .one-line-button {
    min-width: fit-content;
  }
</style>

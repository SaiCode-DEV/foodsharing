<template>
  <div class="card mb-3 rounded">
    <div class="card-header text-white bg-primary">
      <slot name="head-title" />
      <span>
        {{ $i18n('filterlist.some_in_all', {some: storesFiltered.length, all: stores.length}) }}
      </span>
    </div>
    <div
      v-if="stores.length"
      class="card-body p-0"
    >
      <ConfigureableList
        :fields.sync="fields"
        :selection.sync="fieldSelection"
        :state.sync="state"
        store
      >
        <template #head="{ showConfigurationDialog }">
          <div class="form-row p-1 ">
            <div class="d-flex align-items-center col-2">
              <label class=" col-form-label col-form-label-sm">
                {{ $i18n('store.filter') }}
              </label>
            </div>
            <div class="d-flex align-items-center col-4">
              <label class="mb-0">
                <input
                  v-model.trim="state.filterText"
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="Name/Adresse"
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
                :title="$i18n('storelist.emptyfilters')"
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
          >
            <template
              #cell(cooperationStatus)="row"
            >
              <div class="text-center">
                <StoreStatusIcon :cooperation-status="row.value" />
              </div>
            </template>
            <template #cell(memberState)="row">
              {{ getUserRole(row.item.id) }}
            </template>
            <template
              #cell(name)="row"
            >
              <a
                :href="$url('store', row.item.id)"
                class="ui-corner-all"
              >
                {{ row.value }}
              </a>
            </template>
            <template
              #cell(region)="row"
            >
              {{ row.value.name }}
            </template>
            <template
              #cell(actions)="row"
            >
              <b-button
                size="sm"
                @click.stop="row.toggleDetails"
              >
                {{ row.detailsShowing ? 'x' : 'Details' }}
              </b-button>
            </template>
            <template
              #row-details="row"
            >
              <b-card>
                <div class="details">
                  <p>
                    <strong>{{ $i18n('storelist.addressdata') }}</strong><br>
                    {{ row.item.street }} <a
                      :href="mapLink(row.item)"
                      class="nav-link details-nav"
                      :title="$i18n('storelist.map')"
                    >
                      <i class="fas fa-map-marker-alt" />
                    </a><br> {{ row.item.zipCode }} {{ row.item.city }}
                  </p>
                  <p><strong>{{ $i18n('storelist.entered') }}</strong> {{ row.item.createdAt }}</p>
                </div>
              </b-card>
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
      {{ $i18n('store.noStores') }}
      <slot name="no-stores-footer-actions" />
    </div>
  </div>
</template>

<script>
import {
  BPagination,
  BFormSelect,
  VBTooltip,
  BButton,
  BCard,
} from 'bootstrap-vue'
import StoreStatusIcon from './StoreStatusIcon.vue'
import ConfigureableList from '@/components/ConfigureableList.vue'
import BTableMobileFriendly from '@/components/BTableMobileFriendly.vue'
import { useStoreStore } from '@/stores/store'

const storeStore = useStoreStore()

export default {
  components: { BCard, BTableMobileFriendly, BButton, BPagination, BFormSelect, StoreStatusIcon, ConfigureableList },
  directives: { VBTooltip },
  props: {
    stores: { type: Array, required: true },
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
        { value: null, text: 'Status' },
        { value: 1, text: this.$i18n('storestatus.1') }, // CooperationStatus::NO_CONTACT
        { value: 2, text: this.$i18n('storestatus.2') }, // CooperationStatus::IN_NEGOTIATION
        { value: 3, text: this.$i18n('storestatus.3') }, // CooperationStatus::COOPERATION_STARTING
        { value: 4, text: this.$i18n('storestatus.4') }, // CooperationStatus::DOES_NOT_WANT_TO_WORK_WITH_US
        { value: 5, text: this.$i18n('storestatus.5') }, // CooperationStatus::COOPERATION_ESTABLISHED
        { value: 6, text: this.$i18n('storestatus.6') }, // CooperationStatus::GIVES_TO_OTHER_CHARITY
        { value: 7, text: this.$i18n('storestatus.7') }, // CooperationStatus::PERMANENTLY_CLOSED
      ],
      fieldsDefinition: [
        {
          key: 'cooperationStatus',
          label: this.$i18n('storelist.status'),
          tdClass: 'status',
          sortable: true,
        },
        {
          key: 'name',
          label: this.$i18n('storelist.name'),
          sortable: true,
        },
        {
          key: 'street',
          label: this.$i18n('storelist.address'),
          sortable: true,
        },
        {
          key: 'zipCode',
          label: this.$i18n('storelist.zipcode'),
          sortable: true,
        },
        {
          key: 'city',
          label: this.$i18n('storelist.city'),
          sortable: true,
        },

        {
          key: 'createdAt',
          label: this.$i18n('storelist.added'),
          tdClass: 'status',
          sortable: true,
        },
        {
          key: 'region',
          label: this.$i18n('storelist.region'),
          sortable: true,
        },
        {
          key: 'memberState',
          label: this.$i18n('storelist.memberState'),
          tdClass: 'status',
          sortable: true,
        },
        {
          key: 'actions',
          label: this.$i18n('storelist.actions'),
          sortable: false,
        },
      ],
      availableFields: [],
      fieldSelection: [],
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
  },
  created () {
    this.availableFields = this.fieldsDefinition.map(field => field.key)
    this.fieldSelection = this.availableFields
  },
  methods: {
    getUserRole (storeId) {
      if (storeStore.userRelations === null) {
        storeStore.fetchUserStoreRelations()
        return '...loading'
      } else {
        const relation = storeStore.userRelations.find(relation => relation.id === storeId)
        if (relation) {
          if (relation.isManaging) {
            return this.$i18n('store.managing')
          }
          switch (relation.membershipStatus) {
            case 0: return this.$i18n('store.isAppliedForTeam')
            case 1: return this.$i18n('store.member')
            case 2: return this.$i18n('store.jumping')
          }
        }
      }
      // not a member
    },
    fetchData () {
      storeStore.fetchStoresForCurrentUser()
    },
    clearFilter () {
      this.state.filterStatus = null
      this.state.filterText = ''
    },
    mapLink (store) {
      if (['iPad', 'iPhone', 'iPod'].includes(
        navigator?.userAgentData?.platform ||
        navigator?.platform ||
        'unknown')) {
        return `maps://?q=?q=${store.location.lat},${store.location.lon})`
      }

      return `geo:0,0?q=${store.location.lat},${store.location.lon}`
    },
  },
}
</script>
<style>
  .details-nav {
    float:right;
    font-size: 2em;
  }
  .one-line-button {
    min-width: fit-content;
  }
</style>

<template>
  <div>
    <div class="card mb-3 rounded">
      <div class="card-header text-white bg-primary">
        {{ $i18n('chain.listheader') }}
        <span v-if="chains !== null && chains.length !== chainsFiltered.length">
          {{ $i18n('filterlist.some_in_all', { some: chainsFiltered.length, all: chains.length }) }}
        </span>
      </div>
      <div
        v-if="chains !== null"
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
              <div class="col-2 text-center">
                <label class=" col-form-label col-form-label-sm">
                  {{ $i18n('store.filter') }}
                </label>
              </div>
              <div class="col-4">
                <label>
                  <input
                    v-model.trim="state.filterText"
                    type="text"
                    class="form-control form-control-sm"
                    :placeholder="$i18n('chain.filterplaceholder')"
                  >
                </label>
              </div>
              <div class="col-3">
                <b-form-select
                  v-model="state.filterStatus"
                  :options="statusFilterOptions"
                />
              </div>
              <div class="col">
                <button
                  v-b-tooltip.hover
                  type="button"
                  class="btn"
                  :title="$i18n('storelist.emptyfilters')"
                  @click="clearFilter"
                >
                  <i class="fas fa-times" />
                </button>
              </div>
              <div
                v-if="adminPermissions"
                class="col"
              >
                <b-button
                  size="sm"
                  variant="primary"
                  @click="createChainModal"
                >
                  {{ $i18n('chain.new') }}
                </b-button>
              </div>
              <button
                type="button"
                class="btn btn-sm ml-auto shadow-none"
                @click="showConfigurationDialog"
              >
                <i class="fas fa-gear" />
              </button>
            </div>
          </template>
          <b-table-mobile-friendly
            id="chain-list"
            :fields="selectedFields"
            :current-page="state.currentPage"
            :per-page="perPage"
            :items="chainsFiltered"
            tbody-tr-class="chain-row"
            sort-icon-left
            small
            hover
            responsive
          >
            <template #cell(status)="row">
              <i
                v-b-tooltip.hover.window="statusOptions[row.value].description"
                class="fas fa-circle"
                :style="{ color: statusOptions[row.value].color }"
              />
            </template>

            <template #cell(headquartersCity)="row">
              {{ row.value }}
            </template>

            <template #cell(kams)="row">
              <AvatarStack
                :registered-users="row.value"
                :max-width-in-px="100"
              />
            </template>

            <template #cell(name)="row">
              <a
                v-if="row.item.chain.forumThread"
                class="thread-link"
                :href="$url('forumThread', row.item.chain.regionId, row.item.chain.forumThread)"
              >
                {{ row.item.chain.name }}
              </a>
              <span v-else>
                {{ row.item.chain.name }}
              </span>
            </template>

            <template #cell(notes)="row">
              <span class="clamped-3">
                <span v-if="row.item.allowPress">
                  {{ $i18n('chain.allowpress') }}
                </span>
                {{ row.value }}
                <small
                  v-b-tooltip.hover.window="$i18n('chain.tooltips.modificationDate')"
                  class="text-muted change-date"
                >
                  {{ $dateFormatter.date(new Date(row.item.chain.modificationDate), { short: true }) }}
                </small>
              </span>
            </template>

            <template #cell(actions)="row">
              <b-dropdown
                v-if="adminPermissions || row.item.chain.kams.some(kam => kam.id === ownId)"
                v-b-tooltip.hover.noninteractive.window="$i18n('chain.tooltips.options')"
                size="sm"
                no-caret
                variant="primary"
              >
                <template #button-content>
                  <i class="fas fa-cog" />
                </template>
                <b-dropdown-item
                  href="#"
                  @click="detailsChainModal(row)"
                >
                  {{ $i18n('chain.options.showstores') }}
                </b-dropdown-item>
                <b-dropdown-item
                  href="#"
                  @click="editChainModal(row)"
                >
                  {{ $i18n('chain.options.edit') }}
                </b-dropdown-item>
              </b-dropdown>
            </template>
          </b-table-mobile-friendly>
        </ConfigureableList>
        <div class="float-right p-1 pr-3">
          <b-pagination
            v-model="state.currentPage"
            :total-rows="chainsFiltered.length"
            :per-page="perPage"
            aria-controls="chain-list"
            class="my-0"
          />
        </div>
      </div>
      <div
        v-else
        class="card-body d-flex justify-content-center"
      >
        <i class="fas fa-spinner fa-spin" />
      </div>
    </div>

    <InputModal
      ref="input-modal"
      :status-filter-options="statusFilterOptions"
      :admin-permissions="adminPermissions"
    />

    <StoreDetailsModal
      ref="details-modal"
      :store-list="storeList"
    />
  </div>
</template>

<script>

import AvatarStack from '@/components/AvatarStack.vue'
import InputModal from '@/components/Modals/ChainList/InputModal.vue'
import StoreDetailsModal from '@/components/Modals/ChainList/StoreDetailsModal.vue'
import { getters, mutations } from '@/stores/chains'
import { pulseError } from '@/script'
import BTableMobileFriendly from '@/components/BTableMobileFriendly.vue'
import ConfigureableList from '@/components/ConfigureableList.vue'

export default {
  components: { BTableMobileFriendly, AvatarStack, InputModal, StoreDetailsModal, ConfigureableList },
  props: {
    adminPermissions: {
      type: Boolean,
      default: false,
    },
    ownId: {
      type: Number,
      default: -1,
    },
  },
  data () {
    return {
      state: {
        currentPage: 1,
        filterText: '',
        filterStatus: null,
      },
      perPage: 20,
      fieldsDefinition: [
        {
          key: 'status',
          label: this.$i18n('chain.columns.status'),
          tdClass: 'status',
          sortable: true,
          sortByFormatted: true,
          formatter: (value, key, item) => item.chain.status,
        },
        {
          key: 'name',
          label: this.$i18n('chain.columns.name'),
          sortable: true,
          sortByFormatted: (value, key, item) => item.chain.name,
          formatter: (value, key, item) => item,
        },
        {
          key: 'estimatedStoreCount',
          label: this.$i18n('chain.columns.estimatedStoreCount'),
          sortable: true,
          tdClass: 'text-center',
          sortByFormatted: true,
          formatter: (value, key, item) => item.chain.estimatedStoreCount,
        },
        {
          key: 'storeCount',
          label: this.$i18n('chain.columns.stores'),
          sortable: true,
          sortByFormatted: true,
          tdClass: 'text-center',
        },
        {
          key: 'headquartersCity',
          label: this.$i18n('chain.columns.headquarters'),
          sortable: true,
          sortByFormatted: true,
          formatter: (value, key, item) => item.chain.headquartersCountry + ', ' + item.chain.headquartersZip + ' ' + item.chain.headquartersCity,
        },
        {
          key: 'kams',
          label: this.$i18n('chain.columns.kams'),
          formatter: (value, key, item) => item.chain.kams,
        },
        {
          key: 'notes',
          label: this.$i18n('chain.columns.notes'),
          formatter: (value, key, item) => item.chain.notes,
        },
        {
          key: 'actions',
          label: this.$i18n('chain.columns.actions'),
        },
      ],
      statusOptions: [
        {
          description: this.$i18n('chain.status.cooperating'),
          color: 'var(--fs-color-chain-cooperating)',
        },
        {
          description: this.$i18n('chain.status.negotiating'),
          color: 'var(--fs-color-chain-negotiating)',
        },
        {
          description: this.$i18n('chain.status.notcooperating'),
          color: 'var(--fs-color-chain-not-cooperating)',
        },
      ],
      availableFields: [],
      fieldSelection: [],
    }
  },
  computed: {
    chains: () => getters.getChains(),
    storeList: () => getters.getStores(),
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
    chainsFiltered: function () {
      if (this.chains === null) return []
      let chains = this.chains
      const filterText = this.state.filterText.toLowerCase()
      if (filterText) {
        const searchKeys = ['name', 'headquartersCity']
        const searchTerms = filterText.split(/[^a-zA-Z0-9]+/).filter(term => term)
        chains = chains.filter(chain => {
          return searchKeys.some(key => {
            const value = chain.chain[key]?.toLowerCase()
            return searchTerms.every(term => value.includes(term))
          }) || chain.chain.kams.find(kam => kam.id === parseInt(filterText))
        })
      }
      if (this.state.filterStatus !== null) {
        chains = chains.filter(chain => chain.status === this.state.filterStatus)
      }
      return chains.map(chainWithStoreCount => ({ ...chainWithStoreCount, id: chainWithStoreCount.chain.id }))
    },
    statusFilterOptions: function () {
      return [{ value: null, text: this.$i18n('chain.status.filterplaceholder') }].concat(
        this.statusOptions.map((status, index) => ({
          value: index,
          text: status.description,
        })),
      )
    },
  },
  created () {
    mutations.fetchChains()
    this.availableFields = this.fieldsDefinition.map(field => field.key)
    this.fieldSelection = this.availableFields
  },
  methods: {
    clearFilter () {
      this.state.filterStatus = null
      this.state.filterText = ''
    },
    editChainModal (row) {
      const chain = row.item
      const input = {
        name: chain.chain.name,
        headquartersZip: chain.chain.headquartersZip,
        headquartersCity: chain.chain.headquartersCity,
        headquartersCountry: chain.chain.headquartersCountry,
        status: chain.chain.status,
        forumThread: chain.chain.forumThread,
        notes: chain.chain.notes,
        commonStoreInformation: chain.chain.commonStoreInformation,
        estimatedStoreCount: chain.chain.estimatedStoreCount,
        allowPress: !!chain.chain.allowPress,
        kamIds: chain.chain.kams.map(x => x.id),
      }
      this.$refs['input-modal'].show(chain.chain.id, input, this.finishEditing)
    },
    createChainModal () {
      this.$refs['input-modal'].show(-1, {
        name: '',
        headquartersZip: null,
        headquartersCity: '',
        headquartersCountry: '',
        status: 2,
        forumThread: null,
        allowPress: false,
        estimatedStoreCount: 0,
        notes: '',
        commonStoreInformation: '',
        kamIds: [],
      }, this.finishEditing)
    },
    async detailsChainModal (row) {
      const selectedChain = row.item
      this.$refs['details-modal'].show(selectedChain)
      await mutations.fetchChainStores(selectedChain.chain.id)
    },
    async finishEditing (chainId, data) {
      if (chainId < 0) {
        try {
          await mutations.createChain(data)
        } catch (err) {
          const errorDescription = err.jsonContent ?? { message: '' }
          const errorMessagePattern = `chain.errorCodes.${errorDescription.message ?? 'UNKNOWN'}`
          pulseError(this.$i18n('chain.error.create', { error: this.$i18n(errorMessagePattern) }))
          return false
        }
      } else {
        try {
          await mutations.editChain(chainId, data)
        } catch (err) {
          const errorDescription = err.jsonContent ?? { message: '' }
          const errorMessagePattern = `chain.errorCodes.${errorDescription.message ?? 'UNKNOWN'}`
          pulseError(this.$i18n('chain.error.edit', { error: this.$i18n(errorMessagePattern) }))
          return false
        }
      }
      return true
    },
  },
}
</script>

<style lang="scss" scoped>

.status {
  width: 0;
  text-align: center;
}

::v-deep .chain-row td {
  vertical-align: middle;
}

.clamped-3 {
  display: -webkit-inline-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>

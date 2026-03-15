<template>
  <div>
    <div class="card mb-3 rounded">
      <div class="card-header text-white bg-primary">
        {{ $t('chain.listheader') }}
        <span v-if="chains !== null && chains.length !== chainsFiltered.length">
          {{ $t('filterlist.some_in_all', { some: chainsFiltered.length, all: chains.length }) }}
        </span>
      </div>
      <div v-if="chains !== null" class="card-body p-0">
        <ConfigureableList
          :fields.sync="fields"
          :selection.sync="fieldSelection"
          :default-fields="defaultFieldsOrder"
          :state.sync="state"
        >
          <template #head="{ showConfigurationDialog }">
            <div class="d-flex flex-wrap p-2 align-items-center gap-2">
              <div class="d-flex align-items-center justify-items-between gap-2">
                <label for="text-filter" class="flex-shrink-0 col-form-label col-form-label-sm">
                  {{ $t('store.filter') }}
                </label>
                <b-input-group
                  size="sm"
                  class="w-auto"
                >
                  <b-form-input
                    id="text-filter"
                    v-model.trim="state.filterText"
                    type="text"
                    class="form-control flex-grow-1"
                    size="sm"
                    :placeholder="$t('chain.filterplaceholder')"
                  >
                    {{ $t('store.filter') }}
                  </b-form-input>
                  <b-input-group-append
                    v-b-tooltip.hover
                    :title="$t('button.clear_filter')"
                  >
                    <b-button
                      variant="outline-primary"
                      :disabled="state.filterText === ''"
                      @click="state.filterText = ''"
                    >
                      <i class="fas fa-times" />
                    </b-button>
                  </b-input-group-append>
                </b-input-group>
              </div>
              <b-input-group
                size="sm"
                class="w-auto"
              >
                <b-form-select
                  v-model="state.filterStatus"
                  :options="statusFilterOptions"
                />
                <b-input-group-append
                  v-b-tooltip.hover
                  :title="$t('storelist.emptyfilters')"
                >
                  <b-button
                    variant="outline-primary"
                    :disabled="state.filterStatus === null"
                    @click="clearFilter"
                  >
                    <i class="fas fa-times" />
                  </b-button>
                </b-input-group-append>
              </b-input-group>
              <b-button
                type="button"
                size="sm"
                variant="outline-primary"
                class="ml-auto"
                @click="showConfigurationDialog"
              >
                <i class="fas fa-gear" />
              </b-button>
              <b-button
                v-if="adminPermissions"
                size="sm"
                variant="primary"
                class="flex-shrink-0"
                @click="createChainModal"
              >
                {{ $t('chain.new') }}
              </b-button>
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
            @content-overflow="isStoreListOverflowing = $event"
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
                :users="row.value"
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
                  {{ $t('chain.allowpress') }}
                </span>
                {{ row.value }}
                <small
                  v-b-tooltip.hover.window="$t('chain.tooltips.modificationDate')"
                  class="text-muted change-date"
                >
                  {{ $dateFormatter.date(new Date(row.item.chain.modificationDate), { short: true }) }}
                </small>
              </span>
            </template>

            <template #cell(actions)="row">
              <b-dropdown
                v-if="adminPermissions || row.item.chain.kams.some(kam => kam.id === ownId)"
                v-b-tooltip.hover.noninteractive.window="$t('chain.tooltips.options')"
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
                  {{ $t('chain.options.showstores') }}
                </b-dropdown-item>
                <b-dropdown-item
                  href="#"
                  @click="editChainModal(row)"
                >
                  {{ $t('chain.options.edit') }}
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

    <StoreDetailsModal ref="details-modal" :store-list="storeList" />
  </div>
</template>

<script>

import AvatarStack from '@/components/Avatar/AvatarStack.vue'
import InputModal from '@/components/Modals/ChainList/InputModal.vue'
import StoreDetailsModal from '@/components/Modals/ChainList/StoreDetailsModal.vue'
import { getters, mutations } from '@/stores/chains'
import { pulseError } from '@/script'
import BTableMobileFriendly from '@/components/BTableMobileFriendly.vue'
import ConfigureableList from '@/components/ConfigureableList.vue'
import i18n from '@/helper/i18n'

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
          label: this.$t('chain.columns.status'),
          tdClass: 'status text-center',
          sortable: true,
          sortByFormatted: true,
          formatter: (value, key, item) => item.chain.status,
        },
        {
          key: 'name',
          label: this.$t('chain.columns.name'),
          sortable: true,
          sortByFormatted: (value, key, item) => item.chain.name,
          formatter: (value, key, item) => item,
        },
        {
          key: 'estimatedStoreCount',
          label: this.$t('chain.columns.estimatedStoreCount'),
          sortable: true,
          tdClass: 'text-center',
          sortByFormatted: true,
          formatter: (value, key, item) => item.chain.estimatedStoreCount,
        },
        {
          key: 'storeCount',
          label: this.$t('chain.columns.stores'),
          sortable: true,
          sortByFormatted: true,
          tdClass: 'text-center',
        },
        {
          key: 'headquartersCity',
          label: this.$t('chain.columns.headquarters'),
          sortable: true,
          sortByFormatted: true,
          formatter: (value, key, item) => item.chain.headquartersCountry + ', ' + item.chain.headquartersZip + ' ' + item.chain.headquartersCity,
        },
        {
          key: 'kams',
          label: this.$t('chain.columns.kams'),
          formatter: (value, key, item) => item.chain.kams,
        },
        {
          key: 'notes',
          label: this.$t('chain.columns.notes'),
          formatter: (value, key, item) => item.chain.notes,
        },
        {
          key: 'actions',
          label: this.$t('chain.columns.actions'),
          tdClass: 'text-center',
        },
      ],
      statusOptions: [
        {
          description: this.$t('chain.status.cooperating'),
          color: 'var(--fs-color-chain-cooperating)',
        },
        {
          description: this.$t('chain.status.negotiating'),
          color: 'var(--fs-color-chain-negotiating)',
        },
        {
          description: this.$t('chain.status.notcooperating'),
          color: 'var(--fs-color-chain-not-cooperating)',
        },
      ],
      availableFields: [],
      fieldSelection: [],
      isStoreListOverflowing: false,
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
    defaultFieldsOrder () {
      const fieldOrder = ['status', 'name', 'estimatedStoreCount', 'storeCount', 'headquartersCity', 'kams', 'notes', 'actions']
      if (this.isStoreListOverflowing) {
        [fieldOrder[0], fieldOrder[1]] = [fieldOrder[1], fieldOrder[0]] // swap status & name
      }
      return fieldOrder
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
        chains = chains.filter(chain => chain.chain.status === this.state.filterStatus)
      }
      return chains.map(chainWithStoreCount => ({ ...chainWithStoreCount, id: chainWithStoreCount.chain.id }))
    },
    statusFilterOptions: function () {
      return [{ value: null, text: this.$t('chain.status.filterplaceholder') }].concat(
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
        notes: i18n('chain.inputmodal.inputs.notes.default'),
        commonStoreInformation: i18n('chain.inputmodal.inputs.details.default'),
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
          pulseError(this.$t('chain.error.create', { error: this.$t(errorMessagePattern) }))
          return false
        }
      } else {
        try {
          await mutations.editChain(chainId, data)
        } catch (err) {
          const errorDescription = err.jsonContent ?? { message: '' }
          const errorMessagePattern = `chain.errorCodes.${errorDescription.message ?? 'UNKNOWN'}`
          pulseError(this.$t('chain.error.edit', { error: this.$t(errorMessagePattern) }))
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

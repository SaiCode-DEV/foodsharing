<template>
  <b-tab
    ref="tab"
    title-link-class="tab-link"
    title-item-class="tab-item"
    @click="initialize()"
  >
    <template #title>
      <div
        v-b-tooltip:hover.window="$i18n(`pickup.overview.tab.${tabName}.tooltip.${isOwnProfile ? 'own' : 'other'}`)"
      >
        {{ $i18n(`pickup.overview.tab.${tabName}.name`) }}
      </div>
    </template>

    <b-img
      v-if="tableData === null"
      center
      src="/img/469.gif"
    />
    <p v-else-if="tableData.length === 0">
      {{ $i18n(`pickup.overview.tab.${tabName}.empty`) }}
    </p>
    <div v-else class="table-visible-wrapper">
      <PickupTable
        :data="displayedTableData"
        :class="tableClass"
        :fs-id="fsId"
        :allow-slot-cancelation="allowSlotCancelation"
        :paginated="paginated"
        :no-more-pages="nextPage === -1"
        :loading="isFetching"
        @load-more="fetchData"
        @cancel-slot="deleteSlot"
      />
      <div class="options-button-wrapper">
        <b-dropdown
          class="options-dropdown"
          size="sm"
          no-caret
        >
          <template #button-content>
            <i class="fas fa-fw fa-cog" />
          </template>

          <b-dropdown-item-button @click="refresh">
            {{ $i18n('pickup.overview.menu.refresh') }}
          </b-dropdown-item-button>

          <b-dropdown-item-button
            v-if="tabName=='registered' && allowSlotCancelation"
            v-b-modal.cancelAllSlotsModal
            variant="danger"
          >
            {{ $i18n('pickup.overview.menu.signOffAll') }}
            <b-modal
              id="cancelAllSlotsModal"
              :title="$i18n('pickup.overview.modals.signOffAll.title')"
              modal-class="bootstrap"
              header-class="d-flex"
              content-class="pr-3 pt-3"
              centered
              @ok="cancelAllSlots"
            >
              <p class="my-4">
                {{ $i18n('pickup.overview.modals.signOffAll.message') }}
              </p>
            </b-modal>
          </b-dropdown-item-button>

          <b-dropdown-form v-if="tabName=='options'">
            <b-form-checkbox
              id="switch-alignment"
              v-model="showRegistered"
              switch
            >
              {{ $i18n('pickup.overview.menu.registeredSwitch') }}
            </b-form-checkbox>
          </b-dropdown-form>
        </b-dropdown>
      </div>
    </div>
  </b-tab>
</template>

<script>
import { BTab, BFormCheckbox, BDropdown, BDropdownForm, BDropdownItemButton } from 'bootstrap-vue'
import PickupTable from './PickupTable.vue'
import { pulseError } from '@/script'
import { leavePickup, leaveAllPickups } from '@/api/pickups'
const PAGE_SIZE = 50

export default {
  components: { BTab, PickupTable, BFormCheckbox, BDropdown, BDropdownForm, BDropdownItemButton },
  props: {
    tabName: {
      // this text is not displayed but used as a key in the translation files.
      type: String,
      default: () => '',
    },
    dataEndpoint: {
      // api function used to query the data for this tab.
      type: Function,
      required: true,
    },
    tableClass: {
      // css class to apply to the table. Currently used to display registered tabs shadowed in the options tab
      type: String,
      default: () => '',
    },
    init: {
      // whether to load this tabs data initially
      type: Boolean,
      default: () => false,
    },
    paginated: {
      // whether to use this tabs data endpoint paginated
      type: Boolean,
      default: () => false,
    },
    fsId: {
      // the foodsaver id of the shown profile page
      type: Number,
      default: () => -1,
    },
    allowSlotCancelation: {
      // whether to allow canceling slots from this tab (if it's a "registered" tab)
      type: Boolean,
      default: () => false,
    },
    isOwnProfile: {
      // whether the profile view is shown to the owner of the profile. Used to differentiate some texts.
      type: Boolean,
      default: () => false,
    },
  },
  data () {
    return {
      showRegistered: true, // Whether to include registered slots in the table (used in options tab filtering)
      tableData: null, // (possibly empty) Array if data is fetched, null otherwise
      isFetching: false, // whether table data is currently beeing fetched
      nextPage: 0, // The next page that needs to be fetched. -1 if no more pages should be fetched. Only used if paginated
    }
  },
  computed: {
    /**
     * Computes the data handed to the table to be displayed.
     * Currently only filters out registered slots if needed.
     */
    displayedTableData () {
      let data = this.tableData
      if (!this.showRegistered) {
        data = data.filter(slot => slot.isConfirmed === null, data)
      }
      return data
    },
  },
  /**
   * Load data initially once the component is loaded.
   * Only required for the tab, that is open at the start, other tabs data gets loaded once they are opened.
   */
  mounted: function () {
    if (this.init) {
      this.initialize()
    }
  },
  methods: {
    initialize () {
      if (!this.tableData) {
        this.fetchData()
      }
    },
    /**
     * Fetches the tabs data from the defined api endpoint.
     * Also handles pagination and makes sure only one request is made at a time.
     */
    async fetchData () {
      if (this.isFetching || (!this.paginated && this.tableData) || (this.nextPage === -1)) return

      this.isFetching = true

      const page = this.paginated ? this.nextPage++ : undefined
      let data = await this.dataEndpoint(this.fsId, page)

      if (this.paginated) {
        if (data.length < PAGE_SIZE) {
          this.nextPage = -1
        }
        if (this.tableData) {
          data = this.tableData.concat(data)
        }
      }

      this.tableData = data

      this.isFetching = false
    },
    /**
     * Removes an user from a pickup slot.
     * The user is not informed via chat.
     */
    async deleteSlot (item) {
      try {
        await leavePickup(item.store.id, new Date(item.date), this.fsId, 'Removed through user Profile.', false)
        if (this.tableData) {
          const index = this.tableData.findIndex(element => element.store.id === item.store.id && element.date === item.date)
          this.tableData.splice(index, 1)
        }
      } catch (e) {
        console.error(e)
        pulseError(e.message)
      }
    },
    /**
     * Removes an user from all pickup slots.
     * The user is not informed via chat.
     */
    async cancelAllSlots () {
      // console.log('delete all slots')
      try {
        await leaveAllPickups(this.fsId, 'Removed all slot entries through user Profile.')
        if (this.tableData) {
          this.tableData = []
        }
      } catch (e) {
        console.error(e)
        pulseError(e.message)
      }
    },
    /**
     * Resets the data fetching and reloads the data.
     * Will be ignored if already currently fetching.
     */
    refresh () {
      if (!this.isFetching) {
        this.tableData = null
        this.nextPage = 0
        this.fetchData()
      }
    },
  },
}

</script>

<style lang="scss">
.tab-link {
  font-weight: bolder;
  margin-left: 3px;
  &:focus{
    outline: none;
  }
}

.tab-item:first-child>.tab-link {
  margin-left: 10px;
}

#switch-alignment label {
  &::before {
    top: 0;
  }
  &::after {
    top: 2px;
  }
}

.options-button-wrapper {
  position: absolute;
  left: 0;
  top: 0.4rem;
  z-index: 2;
  .options-dropdown {
    button {
      font-size: 14px;
      padding: 3px 5px;
      top: 2px;
    }
    label {
      white-space: nowrap;
    }
  }
}

.table-visible-wrapper {
  position: relative;
}
</style>

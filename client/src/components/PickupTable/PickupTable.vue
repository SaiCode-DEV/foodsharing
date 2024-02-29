<template>
  <b-table
    ref="table"
    hover
    thead-tr-class="unlined"
    sort-icon-left
    sticky-header
    no-border-collapse
    class="pickup-table"
    :items="data"
    :fields="fields"
    :sort-compare="sortCompare"
    :tbody-tr-class="rowClass"
    :class="tableClass"
  >
    <template #cell(confirmed)="entry">
      <i
        v-b-tooltip.hover="iconTooltip(entry)"
        :class="iconClass(entry)"
      />
      <i
        v-if="entry.item.description"
        v-b-tooltip.hover="entry.item.description"
        class="fas fa-info-circle"
      />
    </template>

    <template #cell(date)="entry">
      <span>{{ entry.value }}</span>
    </template>

    <template #cell(store)="entry">
      <a :href="'/?page=fsbetrieb&id='+entry.item.store.id" class="store-name">
        {{ entry.item.store.name }}
      </a>
    </template>

    <template #cell(slots)="entry">
      <AvatarStack
        :users="entry.item.slots.occupied"
        :total-slots="entry.item.slots.max"
        :max-width-in-px="offsetWidth / 5"
      />
    </template>

    <template #cell(slotCancelation)="entry">
      <b-button
        v-if="entry"
        v-b-modal="'cancelSlotModal-'+entry.index+tableId"
        size="sm"
        variant="danger"
      >
        {{ $i18n('pickup.overview.menu.signOff') }}
      </b-button>
      <b-modal
        :id="'cancelSlotModal-'+entry.index+tableId"
        :title="$i18n('pickup.overview.modals.signOff.title')"
        header-class="d-flex"
        content-class="pr-3 pt-3"
        centered
        @ok="$emit('cancel-slot', entry.item)"
      >
        <p class="my-4">
          {{ $i18n('pickup.overview.modals.signOff.message') }}
        </p>
      </b-modal>
    </template>

    <template v-if="paginated" #custom-foot>
      <tr>
        <td colspan="100%" class="table-footer-container">
          <small v-if="noMorePages">
            {{ $i18n('pickup.overview.allLoaded') }}
          </small>
          <b-button
            v-else
            size="sm"
            @click="$emit('load-more')"
          >
            {{ $i18n('pickup.overview.menu.loadMore') }}
          </b-button>
        </td>
      </tr>
    </template>
  </b-table>
</template>

<script>
import { BTable } from 'bootstrap-vue'
import AvatarStack from '@/components/Avatar/AvatarStack.vue'
import i18n from '@/helper/i18n'

const MIN_WIDTH_FOR_WIDE_LAYOUT = 600

export default {
  components: { BTable, AvatarStack },
  props: {
    data: {
      // the data to be displayed in the table
      type: Array,
      default: () => [],
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
    paginated: {
      // whether to use this tabs data endpoint paginated
      type: Boolean,
      default: () => false,
    },
    noMorePages: {
      // whether no more data can be loaded (only relevant if paginated)
      type: Boolean,
      default: () => false,
    },
    tableClass: {
      // css class to apply to the table. Currently used to display registered tabs shadowed in the options tab
      type: String,
      default: () => '',
    },
  },
  data () {
    const fields = [
      {
        key: 'confirmed',
        label: '',
        sortable: false,
        class: 'status-col',
      },
      {
        key: 'date',
        label: i18n('pickup.overview.cols.time'),
        sortable: true,
        formatter: this.formatDate,
      },
      {
        key: 'store',
        label: i18n('pickup.overview.cols.store'),
        sortable: true,
      },
      {
        key: 'slots',
        sortable: false,
        label: i18n('pickup.overview.cols.slots'),
      },
    ]
    if (this.allowSlotCancelation) {
      fields.push({
        key: 'slotCancelation',
        label: '',
        class: 'slot-cancelation',
      })
    }

    return {
      fields, // table column definition
      tableId: Math.random(), // a random (and therefor unique) value used to generate unique element ids
      offsetWidth: 0,
    }
  },
  computed: {
    /**
     * Whether the table should be displayed in a more narrow way.
     */
    narrow () {
      return this.offsetWidth < MIN_WIDTH_FOR_WIDE_LAYOUT
    },
  },
  mounted () {
    window.addEventListener('resize', this.resizeHandler)
    this.resizeHandler()
  },
  methods: {
    /**
     * Method handling the table sorting.
     * Returning undefined results in the default natural sorting.
     * Stores (represented as object in the given data) are sorted by their name
     */
    sortCompare (a, b, key) {
      if (key !== 'store') return undefined
      a = a[key].name
      b = b[key].name
      return (a < b) ? -1 : ((a > b) ? 1 : 0)
    },
    /**
     * Formats a date the way it is supposed to be displayed in the table.
     * Includes weekday, date, month, time and, if not current, year
     * Weekday can be replaced by special forms for today / tomorrow / yesterday.
     * Used strings are translated.
     */
    formatDate (date) {
      return this.$dateFormatter.dateTime(date) + ' ' + i18n('date.clock')
    },
    /**
     * Returns the correct icon tooltip text based on the slot status.
     */
    iconTooltip (item) {
      const confirmed = item.item.confirmed
      let type
      if (confirmed === null) type = 'option'
      else if (confirmed) type = 'confirmed'
      else type = 'pending'
      return i18n('pickup.overview.status.' + type)
    },
    /**
     * Returns the css classes for the slot icon.
     */
    iconClass (item) {
      const confirmed = item.item.confirmed
      const classes = { 'slotstatus-icon fas': true }
      if (confirmed === null) classes['fa-question option'] = true
      else if (confirmed) classes['fa-check-circle confirmed'] = true
      else classes['fa-clock pending'] = true
      return classes
    },
    /**
     * Signal that a slot should be canceled.
     */
    cancelSlot ({ item }) {
      this.$emit('cancel-slot', item)
    },
    /**
     * Returns the css class each row is assigned
     */
    rowClass (item) {
      if (item.confirmed === null) return 'option'
      return 'registered'
    },
    /**
     * Gets the new width of the table.
     */
    resizeHandler () {
      this.offsetWidth = this.$refs.table.$el.clientWidth
    },
  },
}

</script>

<style lang="scss" scoped>
.slotstatus-icon {
  font-size: 14px;
  &.pending {
    color: var(--fs-color-danger-500);
  }
  &.confirmed {
    color: var(--fs-color-secondary-500);
  }
  &.option {
    color: var(--fs-color-primary-500);
  }
}

::v-deep .status-col {
  text-align: center;
}

.shadow-registered .registered td > * {
  opacity: 0.3;
}

.pickup-table .unlined th {
  border-top: 0;
  border-bottom-width: 1px;
  white-space: nowrap;
}

.slot-cancelation {
  width: 0.01%; //content width
  button {
    white-space: nowrap;
  }
}

.table-footer-container {
  text-align: center;
}

.store-name {
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.pickup-table ::v-deep .table tbody td {
  vertical-align: middle;
  padding-left: 0.25rem;
  padding-right: 0.25rem;
}

</style>

<template>
  <div>
    <button
      v-if="contentOverflow"
      type="button"
      class="btn btn-sm btn-secondary btn-block"
      @click="toggleRowExpansionAll"
    >
      {{ allRowsExpanded ? $i18n('collapse_all') : $i18n('expand_all') }}
    </button>
    <b-table
      v-bind="$attrs"
      ref="table"
      :fields="preparedFields"
      :class="{'grid-layout': contentOverflow}"
      :tbody-tr-class="addRowExpandedClass"
      v-on="$listeners"
      @row-clicked="toggleRowExpansion"
    >
      <slot
        v-for="(_, name) in $slots"
        :slot="name"
        :name="name"
      />
      <template
        v-for="(_, name) in $scopedSlots"
        #[name]="slotData"
      >
        <slot
          :name="name"
          v-bind="slotData"
        />
      </template>
    </b-table>
  </div>
</template>

<script>
import { defineComponent } from 'vue'
import { BTable } from 'bootstrap-vue'

export default defineComponent({
  name: 'BTableMobileFriendly',
  components: { BTable },
  inheritAttrs: false,
  props: {
    fields: {
      required: true,
      type: Array,
    },
    itemKey: {
      required: false,
      type: String,
      default: 'id',
    },
  },
  data: () => ({
    contentOverflow: false,
    expandedRows: [],
  }),
  computed: {
    preparedFields: function () {
      return this.fields.map(this.addTdAttr)
    },
    table: function () {
      return this.$refs.table.$el
    },
    allRowsExpanded: function () {
      return this.$attrs.items.length === this.expandedRows.length
    },
  },
  mounted () {
    const resizeObserver = new ResizeObserver(this.onTableResize)
    resizeObserver.observe(this.table)
    this.onTableResize()
  },
  methods: {
    doesElementOverflow (element) {
      return element.clientWidth < element.scrollWidth
    },
    onTableResize () {
      this.contentOverflow = false
      this.$nextTick(() => {
        this.contentOverflow = this.doesElementOverflow(this.table)
      })
    },
    addTdAttr: field => {
      const presentTdAttr = field.tdAttr || {}
      field.tdAttr = { ...presentTdAttr, 'data-th-label': field.label }
      return field
    },
    addRowExpandedClass (item, type) {
      let presentClasses = this.$attrs['tbody-tr-class']
      if (presentClasses === undefined) presentClasses = []
      if (typeof presentClasses === 'string') presentClasses = [presentClasses]
      if (this.expandedRows.includes(item[this.itemKey])) presentClasses.push('expand')
      return presentClasses
    },
    toggleRowExpansion (item, index, event) {
      if (this.contentOverflow) {
        const keyIndex = this.expandedRows.indexOf(item[this.itemKey])
        if (keyIndex === -1) {
          this.expandedRows.push(item[this.itemKey])
        } else {
          this.expandedRows.splice(keyIndex, 1)
        }
      }
    },
    toggleRowExpansionAll () {
      if (this.allRowsExpanded) {
        this.expandedRows = []
      } else {
        this.expandedRows = this.$attrs.items.map(item => item[this.itemKey])
      }
    },
  },
})
</script>

<style lang="scss">
  .grid-layout {
    thead {
      display: none;
    }

    tbody {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); // 18rem is hardcoded for minimum store item / Idk how to make this flexible
      grid-column-gap: 0.15rem;
      background: var(--fs-color-gray-200);
    }

    tr {
      background: var(--white);
      > td {
        display: flex;
      }

      > td {
        display: none;
        &::before {
          content: attr(data-th-label) ':';
          margin-right: auto;
        }
        &:first-child {
          display: flex;
          &::before {
            font-weight: bold;
          }
        }
      }

      &.expand > td {
        display: flex;
      }
    }
  }
</style>

<template>
  <Tree
    v-if="displayed"
    ref="tree"
    :options="treeOptions"
    @node:selected="itemSelected"
  >
    <template #default="{ node }">
      <span>
        <i
          :class="`fas ${iconClass(node.states.type)}`"
        />
        {{ node.text }}
      </span>
    </template>
  </Tree>
</template>

<script>
import { listRegionChildren } from '@/api/regions'
import Tree from 'liquor-tree'
import { REGION_UNIT_TYPE } from '@/stores/regions'

export default {
  components: { Tree },
  props: {
    includeWorkingGroups: { type: Boolean, default: false },

    // if not null, only these types of regions can be selected
    selectableRegionTypes: { type: Array, default: null },
  },
  data () {
    return {
      treeOptions: {
        checkbox: false,
        multiple: false,
        checkOnSelect: false,
        autoCheckChildren: false,
        parentSelect: false,
        fetchData: this.loadData,
      },
      displayed: true,
      regions: [],
    }
  },
  methods: {
    // callback function that loads data for the tree
    async loadData (node) {
      const id = node.id === 'root' ? 0 : node.id

      const regions = await listRegionChildren(id, this.includeWorkingGroups)
      const index = this.regions.findIndex(region => region.id === id)
      const depth = index === -1 ? 0 : this.regions[index].depth + 1
      for (const region of regions) {
        region.depth = depth
      }
      this.regions.splice(index + 1, 0, ...regions)
      this.$emit('update')
      return regions.map(region => {
        return {
          id: region.id,
          text: region.name,
          isBatch: region.hasChildren,
          children: [],
          regions: region,
          state: {
            selectable: this.selectableRegionTypes === null || this.selectableRegionTypes.includes(region.type),
            type: region.type,
          },
        }
      })
    },
    itemSelected (node) {
      this.$emit('change', node)
    },
    iconClass (regionType) {
      switch (regionType) {
        case REGION_UNIT_TYPE.CITY:
          return 'fa-city'
        case REGION_UNIT_TYPE.PART_OF_TOWN:
          return 'fa-building'
        case REGION_UNIT_TYPE.WORKING_GROUP:
          return 'fa-users'
        default:
          return 'fa-globe'
      }
    },
    async reset () {
      this.displayed = false
      this.regions = []
      await this.$nextTick()
      this.displayed = true
    },
  },
}
</script>
<style scoped lang="scss">
::v-deep {
  .tree-anchor { margin-left: -0.5em }
  .tree-content {
    padding-left: 0.5em !important;
  }
  .tree-children { margin-left: 1.5em }
  .tree-node.selected > .tree-content {
    background-color: var(--fs-color-success-alpha-30);
    border-radius: var(--border-radius);
  }
  .tree > .tree-root {
    margin: 0;
  }
}
</style>

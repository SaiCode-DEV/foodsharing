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
      const id = node.id === 'root' ? 0 : node.states.id

      const regions = await listRegionChildren(id, this.includeWorkingGroups)
      this.$emit('update')
      return regions.map(region => {
        return {
          text: region.name,
          isBatch: region.hasChildren,
          children: [],
          state: {
            selectable: this.selectableRegionTypes === null || this.selectableRegionTypes.includes(region.type),
            type: region.type,
            id: region.id,
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
      await this.$nextTick()
      this.displayed = true
    },
    async deleteRegion (region) {
      this.$refs.tree.remove({ states: { id: region.id } })
      this.$emit('update')
    },
    async addRegion (region) {
      let parent = this.$refs.tree.find({ states: { id: region.parentId } })[0]
      parent?.expand?.()
      parent ??= this.$refs.tree
      const regionNode = {
        text: region.name,
        state: {
          type: region.type,
          id: region.id,
          selected: true,
        },
      }
      this.addRegionNode(regionNode, parent, region.name)
      this.$emit('update')
    },
    async updateRegion (region) {
      let newParent = this.$refs.tree.find({ states: { id: region.parentId } })[0]
      newParent?.expand?.()
      newParent ??= this.$refs.tree
      const node = this.$refs.tree.find({ states: { id: region.id } })[0]
      node.data.text = region.name
      node.states.type = region.type
      this.$refs.tree.remove(node)
      this.addRegionNode(node, newParent, region.name)
      this.$emit('update')
    },
    addRegionNode (regionNode, parent, regionName) {
      const nextSibling = parent.children.find(child => child.text > regionName)
      if (nextSibling) {
        nextSibling.before(regionNode)
      } else {
        parent.append(regionNode)
      }
    },
    getRegions () {
      const elements = this.$refs.tree.toJSON().map(x => [x, 1])
      const regions = []
      while (elements.length) {
        const [element, depth] = elements.shift()
        elements.unshift(...element.children.map(x => [x, depth + 1]))
        regions.push({
          name: element.data.text,
          id: element.state.id,
          depth: depth,
        })
      }
      return regions
    },
    unselect () {
      this.$refs.tree.find({ states: { selected: true } })[0]?.unselect?.()
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

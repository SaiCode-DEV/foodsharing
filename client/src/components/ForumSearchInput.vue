<!-- input field that allows searching for matchingForums and shows suggestions -->
<template>
  <div>
    <vue-simple-suggest
      v-if="!loading"
      ref="simpleSuggest"
      v-model="forum"
      :list="search"
      :max-suggestions="10"
      :min-length="0"
      :debounce="200"
      :filter-by-query="false"
      mode="select"
      :nullable-select="true"
      value-attribute="id"
      display-attribute="name"
      :styles="autoCompleteStyle"
      :controls="controls"
      @select="handleSelection"
    >
      <input
        type="text"
        class="form-control with-border"
        :placeholder="placeholder"
        :value="initialForum ? initialForum.name : forum ? forum.name : null"
      >
    </vue-simple-suggest>
  </div>
</template>

<script>
import VueSimpleSuggest from 'vue-simple-suggest'
import { getThread } from '@/api/forum'
import { searchForum } from '@/api/search'
import { pulseError } from '@/script'
import { useRegionStore } from '@/stores/regions'
import { getters } from '@/stores/groups'

const regionStore = useRegionStore()
const groups = getters.get()

export default {
  components: { VueSimpleSuggest },
  props: {
    placeholder: { type: String, default: '' },
    filter: { type: Function, default: null },
    /**
     * If not null, the search is restricted to these regions.
     */
    regionIds: { type: Array, default: null },
    value: { type: Number, default: 0 },
  },
  data () {
    return {
      loading: true,
      forum: null,
      initialForum: null,
      autoCompleteStyle: {
        inputWrapper: 'input-group',
        suggestions: 'position-absolute list-group',
        suggestItem: 'list-group-item',
      },
      controls: {
        // Using the values form user-search-input
        selectionUp: [38, 33],
        selectionDown: [40, 34],
        select: [13, 36],
        showList: [40],
        hideList: [27, 35],
      },
    }
  },
  computed: {
    searchRegions () {
      const myRegions = regionStore.regions.map(region => region.id)
      const myGroups = groups.map(group => group.id)
      return [...new Set(this.regionIds).intersection(new Set([...myRegions, ...myGroups]))]
    },
  },
  mounted () {
    this.loadInitialForumInformation()
  },
  methods: {
    formatItem (item) {
      item.name = item.name + ' (' + item.id + ')'
      return item
    },
    async search (query) {
      // requests search results from the server
      let matchingForums = []
      const isNumber = /^\d+\.?\d*$/.test(query)
      if (isNumber) {
        const thread = (await getThread(Number(query))).data
        if (this.searchRegions.includes(thread.regionId)) {
          matchingForums.push({ id: thread.id, name: thread.title })
        }
      } else if (query.length >= 3) {
        try {
          matchingForums = (await Promise.all(this.searchRegions.map(regionId => searchForum(regionId, 0, query)))).flat()
          if (this.filter) {
            // let the external function filter by forum id
            const filteredIds = matchingForums.map(x => x.id).filter(this.filter)
            matchingForums = matchingForums.filter(x => filteredIds.includes(x.id))
          } else {
            this.forum = null
          }
        } catch (e) {
          pulseError(this.$i18n('error_unexpected'))
        }
      }
      return matchingForums.map(this.formatItem)
    },
    async loadInitialForumInformation () {
      if (this.value) {
        const forumInformation = (await getThread(this.value)).data
        this.forum = this.formatItem({ id: forumInformation.id, name: forumInformation.title })
        this.initialForum = this.forum
      }
      this.loading = false
    },
    handleSelection (suggest) {
      if (suggest) {
        this.forum = suggest
        this.initialForum = this.forum
        this.$emit('input', suggest.id)
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.vue-simple-suggest::v-deep {
  .input-wrapper {
    border: 0;
    padding: 0;
  }

  .suggestions {
    z-index: 1000;
    margin: 0;
  }

  .suggest-item {
    display: inline-block;
    line-height: 1;
    max-width: 100%;
    text-overflow: ellipsis;
  }

  .suggest-item.hover {
    border: 1px solid var(--fs-color-secondary-500);
    background-color: var(--fs-color-secondary-500);
    color: white;
  }
}

.vue-simple-suggest.focus::v-deep {
  background-color: white !important;
  border: 0;
}

.vue-simple-suggest-enter-active.suggestions,
.vue-simple-suggest-leave-active.suggestions {
  transition: opacity .2s;
}

.vue-simple-suggest-enter.suggestions,
.vue-simple-suggest-leave-to.suggestions {
  opacity: 0 !important;
}
</style>

<template>
  <b-modal
    id="searchBarModal"
    ref="searchBarModal"
    button-size="sm"
    size="lg"
    @shown="shownHandler"
  >
    <template #modal-header>
      <label
        class="sr-only"
        for="searchField"
        v-text="$i18n('search.placeholder')"
      />
      <div class="search-bar-wrapper">
        <i
          class="icon fas"
          :class="{
            'fa-search': !isLoading,
            'fa-spinner fa-spin': isLoading,
          }"
        />
        <input
          id="searchField"
          ref="searchField"
          v-model="query"
          type="text"
          class="form-control"
          :placeholder="$i18n('search.placeholder')"
          tabindex="1"
        >
        <i
          class="icon icon-right fas"
          :class="{
            'fa-times is-clickable': query.length > 0,
          }"
          @click="query=''"
        />
      </div>
      <b-button
        v-if="isOrga"
        v-b-tooltip.bottom.ds1000.hover="$i18n(`search.scope.${globalSearch ? 'global' : 'local'}`)"
        :variant="globalSearch ? 'danger' : 'outline-primary'"
        class="ml-2 p-0 global-search-btn"
        @click="globalSearch = !globalSearch"
      >
        <i :class="{ fas: true, 'fa-globe': globalSearch, 'fa-street-view': !globalSearch }" />
      </b-button>
    </template>
    <template
      #default
    >
      <SearchResults
        v-if="showResults"
        class="results"
        :results="results"
        :is-loading="isLoading"
        @close="$refs.searchBarModal.hide"
      />
      <div
        v-else
        class="alert alert-info"
      >
        <span v-text="$i18n('search.informations')" />
        <span
          v-if="idle && query.length"
          v-text="$i18n('search.too_short')"
        />
      </div>
    </template>
    <template #modal-footer="{ hide }">
      <b-button
        size="sm"
        variant="secondary"
        @click="hide('forget')"
      >
        {{ $i18n('globals.close') }}
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import SearchResults from '@/components/SearchBar/SearchResults'
import { search, getSearchIndex } from '@/api/search'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
import DataUser, { mutations as userStoreMutations } from '@/stores/user.js'
const cacheRequestName = 'searchIndex'
const rateLimitInterval = 1000 * 60 * 5 // 5 minutes in milliseconds

export default {
  components: { SearchResults },
  data () {
    userStoreMutations.fetchDetails()
    return {
      query: '',
      showResults: false,
      isLoading: false,
      directSearchResults: null,
      index: null,
      recentQueryChangesCount: 0,
      globalSearch: false,
    }
  },
  computed: {
    strippedQuery () {
      let queryWords = this.query.toLowerCase().split(/[,;\s]+/g).sort((a, b) => a.length - b.length)
      if (queryWords.length > 1) {
        // Remove query words that are substrings of others
        queryWords = queryWords.filter((word, i) => !queryWords.slice(i + 1).some(otherWord => otherWord.includes(word)))
      }
      return queryWords.join(' ')
    },
    results () {
      if (!this.index) {
        return this.directSearchResults
      }
      const results = {}
      const queryWords = this.strippedQuery.split(' ')
      const detailedSearch = queryWords.length > 1
      for (const key in this.index) {
        // Search local index for results
        results[key] = this.index[key].filter(
          entry => queryWords.every(word => this.searchString(entry.search_string, detailedSearch).includes(this.collateString(word))),
        )
        if (this.directSearchResults) {
          // Replace local result entries by search results, since they may be more recent
          results[key] = results[key].map(localEntry => this.directSearchResults[key].find(entry => entry.id === localEntry.id) ?? localEntry)
          // Append additional search results that were not found locally
          results[key].push(...this.directSearchResults[key].filter(
            entry => !results[key].some(indexedEntry => entry.id === indexedEntry.id),
          ))
        }
      }

      // Chats in the local search index are sorted by recency, not by member count. Therefor it needs to be reordered
      results.chats.sort((a, b) => a.member_count - b.member_count)
      return results
    },
    idle () {
      return this.recentQueryChangesCount === 0
    },
    isOrga () {
      return DataUser.getters.isOrga()
    },
  },
  watch: {
    strippedQuery (strippedQuery) {
      // Require at least one word of length 3 or two of length 2:
      const queryLengthScore = strippedQuery.split(' ').map(word => word.length - 1).reduce((a, b) => a + b)
      if (queryLengthScore > 1) {
        this.showResults = true
        this.delayedFetch(strippedQuery)
        return
      }
      clearTimeout(this.timeout)
      this.showResults = false
      this.isLoading = false
      this.directSearchResults = null
    },
    async query (query) {
      this.recentQueryChangesCount++
      await new Promise(resolve => window.setTimeout(resolve, 2000))
      this.recentQueryChangesCount--
    },
  },
  methods: {
    shownHandler () {
      this.focusSearchbar()
      this.fetchIndex()
    },
    focusSearchbar () {
      this.$refs.searchField.focus()
    },
    delayedFetch (strippedQuery) {
      this.isLoading = true
      this.directSearchResults = undefined
      if (this.timeout) {
        clearTimeout(this.timeout)
      }
      this.timeout = setTimeout(() => {
        this.fetch(strippedQuery)
      }, 200)
    },
    async fetch (strippedQuery) {
      const results = await search(strippedQuery, this.globalSearch)
      if (strippedQuery !== this.strippedQuery) {
        // query has changed, throw away this response
        return false
      }
      this.directSearchResults = results
      this.isLoading = false
    },
    async fetchIndex () {
      const cacheOutdated = await getCacheInterval(cacheRequestName, rateLimitInterval)
      if (this.index && !cacheOutdated) return
      this.index = await getCache(cacheRequestName)
      if (!this.index || cacheOutdated) {
        this.index = await getSearchIndex()
        setCache(cacheRequestName, this.index)
      }
    },
    collateString (string) {
      return string.toLowerCase().normalize('NFKD').replace(/[^\w\d\s"]/g, '')
    },
    searchString (string, detailedSearch) {
      if (!detailedSearch) string = string.split('"!!!"')[0]
      return this.collateString(string)
    },
  },
}
</script>

<style lang="scss" scoped>
.is-clickable {
  cursor: pointer;
}

.icon {
  position: absolute;
  left: .25rem;
  font-size: 1.15rem;
  color: var(--fs-color-dark);
}

.icon-right {
  left: unset;
  right: .25rem;
}

::v-deep .modal-header {
  align-items: center;
  background-color: var(--fs-color-light);
  position: relative;
}

::v-deep.input-group-text {
  border: 0;
  background-color: var(--fs-color-transparent);

  i {
    min-width: 1rem;
  }
}

::v-deep.form-control {
  font-size: 1.5rem;
  border: 0;
  padding-inline: 2.75rem;

  @media (max-width: 575.98px) {
    font-size: 1rem;
  }
}

::v-deep .alert {
  margin-bottom: 0;
}

::v-deep.results > .entry > .dropdown-item,
::v-deep.results > .entry > .dropdown-header {
  padding-left: 0;
  padding-right: 0;
}

.global-search-btn {
  width: 2.5em;
  height: 2.5em;
}

.search-bar-wrapper {
  display: flex;
  position: relative;
  flex-grow: 1;
  align-items: center;
}

</style>

<template>
  <Container
    tag="dashboard.activity_overview"
    :title="selectedFilter ? $t('dashboard.updates_title_some', [$t(selectedFilter.text)]) : $t('dashboard.updates_title_all')"
    :toggle-visiblity="false"
  >
    <div
      v-if="activeFilters.length > 1"
      class="activity-options list-group-item justify-content-between border-bottom-0"
    >
      <div class="d-flex align-items-center">
        <button
          v-for="(filter, key) in activeFilters"
          :key="key"
          v-b-tooltip="$t(filter.text)"
          :class="{'btn-primary': isActiveFilter(filter.type)}"
          class="btn btn-sm btn-icon"
          @click="setFilter(filter.type)"
        >
          <i
            v-if="filter.icon"
            class="fas fa-fw"
            style="min-width: 1.5rem;"
            :class="[filter.icon]"
          />
          <span
            v-else
            v-text="$t(filter.text)"
          />
          <span
            class="sr-only"
            v-text="$t(filter.text)"
          />
        </button>
      </div>
      <button
        id="activity-option"
        v-b-tooltip="$t('dashboard.settings_tooltip')"
        :class="{'btn-primary': showListings}"
        class="btn btn-sm btn-icon"
        @click="toggleOptionListings"
      >
        <span
          class="sr-only"
          v-text="$t('dashboard.settings')"
        />
        <i
          class="fas"
          style="min-width: 1rem;"
          :class="{
            'fa-times': showListings,
            'fa-cog': !showListings,
          }"
        />
      </button>
    </div>
    <div v-if="showListings" class="list-group-item">
      <ActivityOptionListings
        @close="showListings = false"
        @reload-data="$refs.thread.reloadData()"
      />
    </div>
    <ActivityThread
      id="activity"
      ref="thread"
      :class="{'muted': showListings}"
      :displayed-type="selectedType"
    />
  </Container>
</template>

<script>
import { useUserStore } from '@/stores/user'
import Container from '../Container.vue'
import ActivityThread from './ActivityThread'
import ActivityOptionListings from './ActivityOptionListings'

const userStore = useUserStore()

export default {
  components: { Container, ActivityThread, ActivityOptionListings },
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      filters: [
        {
          type: 'all',
          text: 'dashboard.display_all',
        },
        {
          type: 'forum',
          icon: 'fa-comment-alt',
          text: 'terminology.forum',
        },
        {
          type: 'event',
          icon: 'fa-calendar-alt',
          text: 'terminology.events',
        },
        {
          type: 'friendWall',
          icon: 'fa-user',
          text: 'terminology.wall',
        },
        {
          onlyForFoodsharer: true,
          type: 'foodsharepoint',
          icon: 'fa-recycle',
          text: 'terminology.fsp',
        },
        {
          type: 'mailbox',
          icon: 'fa-envelope',
          text: 'terminology.mailboxes',
        },
        {
          type: 'store',
          icon: 'fa-shopping-cart',
          text: 'terminology.stores',
        },
      ],
      selectedType: 'all',
      showListings: false,
    }
  },
  computed: {
    isFoodsaver () {
      return userStore.isFoodsaver
    },
    activeFilters () {
      if (!this.isFoodsaver) {
        return this.filters.filter(filter => filter.onlyForFoodsharer)
      }
      return this.filters
    },
    selectedFilter () {
      return this.filters.find(f => f.type === this.selectedType && this.selectedType !== 'all')
    },
  },
  created () {
    const type = localStorage.getItem('activity-selected-type')
    if (this.isFoodsaver && type !== null && type !== 'null') {
      this.filtering(type)
    } else {
      this.filtering(this.activeFilters[0].type)
    }
  },
  methods: {
    setFilter (filter) {
      localStorage.setItem('activity-selected-type', filter)
      this.filtering(filter)
    },
    filtering (category) {
      this.selectedType = category
    },
    toggleOptionListings () {
      this.showListings = !this.showListings
    },
    isActiveFilter (category) {
      return category === this.selectedType
    },
  },
}
</script>

<style lang="scss" scoped>
.activity-options {
  display: flex;

  .btn:not(:first-child) {
    padding: 0.15rem 0.25rem;
  }
}

.muted {
  opacity: 0.5;
  pointer-events: none;
  user-select: none;
}

.btn-icon {
  @media (min-width: 768px) and (max-width: 992px) {
    padding: 0.5rem 0.35rem;
  }
}
</style>

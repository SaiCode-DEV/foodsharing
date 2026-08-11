<template>
  <div>
    <Dropdown
      :title="$t('menu.entry.baskets')"
      icon="fa-shopping-basket"
      :badge="basketsRequestCount"
      is-fixed-size
      is-scrollable
      class="testing-basket-dropdown"
    >
      <template
        v-if="baskets.length > 0"
        #content
      >
        <BasketsEntry
          v-for="basket in baskets"
          :key="basket.id"
          :basket="basket"
          @basket-remove="openRemoveBasketModal"
        />
      </template>
      <template v-else #content>
        <small
          role="menuitem"
          class="disabled dropdown-item"
          v-text="$t('basket.my_list_empty')"
        />
      </template>
      <template #actions>
        <button
          v-b-modal="'addBasketModal'"
          role="menuitem"
          class="testing-basket-create dropdown-item dropdown-action"
        >
          <i class="icon-subnav fas fa-plus" />
          {{ $t('basket.add') }}
        </button>
        <FsLink
          :to="$url('baskets')"
          role="menuitem"
          class="dropdown-item dropdown-action"
        >
          <i class="icon-subnav fas fa-list" />
          {{ $t('basket.all') }}
        </FsLink>
        <button
          class="dropdown-item dropdown-action"
          :disabled="!mayRefresh"
          @click="refresh"
        >
          <i class="icon-subnav fas fa-refresh" />
          {{ $t('menu.entry.refresh') }}
          <TimeDisplay :time="fetchedTime" class="float-right" />
        </button>
      </template>
    </Dropdown>
    <AddBasketModal />
    <RemoveBasketRequestModal v-if="selectedRequest !== null" :request="selectedRequest" />
  </div>
</template>
<script>
// Stores
import { useBasketStore } from '@/stores/baskets'
// Components
import Dropdown from '../_NavItems/NavDropdown'
import BasketsEntry from './NavBasketsEntry'
// Others
import AddBasketModal from '@/views/partials/Modals/AddBasketModal.vue'
import RemoveBasketRequestModal from '@/views/partials/Modals/RemoveBasketRequestModal.vue'
import TimeDisplay from '@/components/TimeDisplay.vue'
import FsLink from '@/components/UI/FsLink.vue'
const REFRESH_WAIT_TIME = 20_000

export default {
  components: { BasketsEntry, Dropdown, AddBasketModal, RemoveBasketRequestModal, TimeDisplay, FsLink },
  setup () {
    return {
      basketStore: useBasketStore(),
    }
  },
  data () {
    return {
      selectedRequest: null,
      fetchedTime: null,
      mayRefresh: false,
    }
  },
  computed: {
    baskets () {
      return this.basketStore.getOwn
    },
    basketsRequestCount () {
      return this.basketStore.getRequestedCount
    },
  },
  async mounted () {
    await this.basketStore.fetchOwn()
    const age = await this.basketStore.getOwnCacheAge()
    this.updateMayRefresh(age)
  },
  methods: {
    openRemoveBasketModal (request) {
      this.selectedRequest = request
      this.$nextTick(() => {
        this.$bvModal.show('RemoveBasketRequestModal')
      })
    },
    async refresh () {
      await this.basketStore.fetchOwn(true)
      this.updateMayRefresh(0)
    },
    async updateMayRefresh (age) {
      this.mayRefresh = age > REFRESH_WAIT_TIME
      this.fetchedTime = new Date(Date.now() - age)
      if (age < REFRESH_WAIT_TIME) {
        await new Promise(resolve => window.setTimeout(resolve, REFRESH_WAIT_TIME - age))
        this.mayRefresh = true
      }
    },

  },
}
</script>

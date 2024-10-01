<template>
  <div>
    <Dropdown
      :title="$i18n('menu.entry.baskets')"
      icon="fa-shopping-basket"
      :badge="basketsRequestCount"
      is-fixed-size
      is-scrollable
      class="testing-basket-dropdown"
    >
      <template
        v-if="basketsSorted.length > 0"
        #content
      >
        <BasketsEntry
          v-for="basket in basketsSorted"
          :key="basket.id"
          :basket="basket"
          @basket-remove="openRemoveBasketForm"
        />
      </template>
      <template v-else #content>
        <small
          role="menuitem"
          class="disabled dropdown-item"
          v-text="$i18n('basket.my_list_empty')"
        />
      </template>
      <template #actions>
        <button
          v-b-modal="'addBasketModal'"
          role="menuitem"
          class="testing-basket-create dropdown-item dropdown-action"
        >
          <i class="icon-subnav fas fa-plus" />
          {{ $i18n('basket.add') }}
        </button>
        <a
          :href="$url('baskets')"
          role="menuitem"
          class="dropdown-item dropdown-action"
        >
          <i class="icon-subnav fas fa-list" />
          {{ $i18n('basket.all') }}
        </a>
      </template>
    </Dropdown>
    <AddBasketModal />
  </div>
</template>
<script>
// Stores
import { getters } from '@/stores/baskets'
// Components
import Dropdown from '../_NavItems/NavDropdown'
import BasketsEntry from './NavBasketsEntry'
// Others
import { ajreq } from '@/script'
import AddBasketModal from '@/views/partials/Modals/AddBasketModal.vue'

export default {
  components: { BasketsEntry, Dropdown, AddBasketModal },
  computed: {
    baskets () {
      return getters.getOwn()
    },
    basketsSorted () {
      return this.baskets.slice().sort((a, b) => {
        const aD = new Date(a.updatedAt)
        const bD = new Date(b.updatedAt)
        if (aD.getTime() === bD.getTime()) return 0
        return bD > aD ? 1 : -1
      })
    },
    basketsRequestCount () {
      return getters.getRequestedCount()
    },
  },
  methods: {
    openRemoveBasketForm (basketId, userId) {
      ajreq('removeRequest', {
        app: 'basket',
        id: basketId,
        fid: userId,
      })
    },
  },
}
</script>

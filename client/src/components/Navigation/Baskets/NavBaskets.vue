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
          @basket-remove="openRemoveBasketModal(basket)"
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
    <RemoveBasketRequestModal v-if="selectedBasket !== null" :basket="selectedBasket" />
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

export default {
  components: { BasketsEntry, Dropdown, AddBasketModal, RemoveBasketRequestModal },
  setup () {
    const basketStore = useBasketStore()
    return {
      basketStore,
    }
  },
  data () {
    return {
      selectedBasket: null,
    }
  },
  computed: {
    baskets () {
      return this.basketStore.getOwn
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
      return this.basketStore.getRequestedCount
    },
  },
  methods: {
    openRemoveBasketModal (basket) {
      this.selectedBasket = basket
      this.$nextTick(() => {
        this.$bvModal.show('RemoveBasketRequestModal')
      })
    },
  },
}
</script>

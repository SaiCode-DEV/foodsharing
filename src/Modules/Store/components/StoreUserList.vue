<template>
  <div>
    <StoreListComponent
      v-if="isOwnUserId || user"
      :stores="stores"
      config-store-key="OwnStores"
    >
      <template #head-title>
        <span>
          {{ isOwnUserId ? $t('store.ownStores') : $t('store.storeFrom', { name: user.name }) }}
        </span>
      </template>
    </StoreListComponent>
  </div>
</template>

<script>
import StoreListComponent from './StoreListComponent.vue'
import { hideLoader, showLoader } from '@/script'
import { useStoreStore } from '@/stores/store'
import { useUserStore } from '@/stores/user'
import { getBasicUser } from '@/api/user'

const storeStore = useStoreStore()
const userStore = useUserStore()

export default {
  components: { StoreListComponent },
  props: { userId: { type: Number, required: true } },
  data () {
    return {
      user: null,
    }
  },
  computed: {
    stores: () => storeStore.userStores,
    isOwnUserId () {
      return this.userId === userStore.getUserId
    },
  },
  async created () {
    if (!this.stores.length) {
      showLoader()
      this.isBusy = true
      this.user = await getBasicUser(this.userId)
      await Promise.all([
        storeStore.fetchUserStoreRelations(this.userId),
        storeStore.fetchStoresForUser(this.userId),
      ])
      this.isBusy = false
      hideLoader()
    }
  },
}
</script>

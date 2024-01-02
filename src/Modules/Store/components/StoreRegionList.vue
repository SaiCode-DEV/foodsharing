<template>
  <div>
    <StoreListComponent :stores="stores">
      <template #head-title>
        <span>
          {{ $i18n('store.allStoresOfRegion') }} {{ regionName }}
        </span>
      </template>
      <template #header-actions>
        <div
          :regionId="regionId"
          class="col one-line-button"
        >
          <a
            :href="$url('storeAdd', regionId)"
            class="btn btn-mb btn-primary btn-block"
          >
            {{ $i18n('store.addNewStoresButton') }}
          </a>
        </div>
      </template>
      <template #no-stores-footer-actions>
        <div
          :regionId="regionId"
          class="col"
        >
          <a
            :href="$url('storeAdd', regionId)"
            class="btn btn-sm btn-primary btn-block"
          >
            {{ $i18n('store.addNewStoresButton') }}
          </a>
        </div>
      </template>
    </StoreListComponent>
  </div>
</template>

<script>
import StoreListComponent from './StoreListComponent.vue'
import { hideLoader, showLoader } from '@/script'
import { useStoreStore } from '@/stores/store'

const storeStore = useStoreStore()

export default {
  components: { StoreListComponent },
  props: {
    showCreateStore: { type: Boolean, default: false },
    regionId: { type: Number, default: 0 },
    regionName: { type: String, default: '' },
  },
  data () {
    return {}
  },
  computed: {
    stores: () => storeStore.regionStores,
  },
  async created () {
    showLoader()
    this.isBusy = true
    await storeStore.fetchStoresForRegion(this.regionId)
    this.isBusy = false
    hideLoader()
  },
}
</script>

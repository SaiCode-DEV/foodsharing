<template>
  <div>
    <StoreListComponent
      v-if="!isDeactivatedRegion"
      :stores="stores"
      :show-member-state="false"
    >
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
        <div :regionId="regionId" class="col">
          <a
            :href="$url('storeAdd', regionId)"
            class="btn btn-sm btn-primary btn-block"
          >
            {{ $i18n('store.addNewStoresButton') }}
          </a>
        </div>
      </template>
    </StoreListComponent>
    <b-alert
      v-else
      variant="info"
      show
    >
      {{ $i18n('store.deactivatedRegion') }}
    </b-alert>
  </div>
</template>

<script>
import StoreListComponent from './StoreListComponent.vue'
import { hideLoader, showLoader } from '@/script'
import { useStoreStore } from '@/stores/store'
import { REGION_IDS } from '@/consts'

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
    /*
     @TODO: This deactivates store lists for Europe and countries because it needs to much memory on the server.
     Can be remove when there is pagination.
     */
    isDeactivatedRegion () {
      return [REGION_IDS.EUROPE, REGION_IDS.GERMANY, REGION_IDS.AUSTRIA, REGION_IDS.SWITZERLAND]
        .indexOf(this.regionId) >= 0
    },
  },
  async created () {
    if (!this.isDeactivatedRegion) {
      showLoader()
      this.isBusy = true
      await storeStore.fetchStoresForRegion(this.regionId)
      this.isBusy = false
      hideLoader()
    }
  },
}
</script>

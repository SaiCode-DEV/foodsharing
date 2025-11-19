<template>
  <div>
    <PickupModal
      modal-id="AddPickupModal"
      :store-id="storeId"
      :title="$t('store.enter_date')"
      :description="$t('store.enter_date_description')"
    />
  </div>
</template>

<script>
import PickupModal from './PickupModal.vue'

export default {
  components: { PickupModal },
  props: {
    storeId: { type: Number, required: true },
  },
}
</script>

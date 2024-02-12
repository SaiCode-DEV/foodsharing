<template>
  <!-- eslint-disable vue/max-attributes-per-line -->
  <div>
    <b-button v-if="mayEdit" v-b-modal="'addBasketModal'" block variant="primary">
      <i class="fas fa-pen" />
      {{ $i18n('basket.edit') }}
    </b-button>
    <b-button block variant="outline-danger" @click="deleteBasket">
      <i class="fas fa-trash" />
      {{ $i18n('basket.delete') }}
    </b-button>
    <AddBasketModal :edit="true" :basket="basket" />
  </div>
</template>

<script>
import { removeBasket } from '@/api/baskets'
import AddBasketModal from '@/views/partials/Modals/AddBasketModal.vue'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

export default {
  components: { AddBasketModal },
  mixins: [ConfirmationDialogue],
  props: {
    basket: { type: Object, required: true },
    mayEdit: { type: Boolean, default: false },
  },
  methods: {
    async deleteBasket () {
      if (!await this.confirmationDialogue('basket.delete_confirmation.text')) return
      await removeBasket(this.basket.id)
      location.href = this.$url('baskets')
    },
  },
}
</script>

<template>
  <div>
    <b-button
      v-if="mayEdit"
      v-b-modal="'addBasketModal'"
      block
      variant="primary"
    >
      <i class="fas fa-pen" />
      {{ $i18n('basket.edit') }}
    </b-button>
    <b-button
      block
      variant="outline-danger"
      @click="deleteBasket"
    >
      <i class="fas fa-trash" />
      {{ $i18n('basket.delete') }}
    </b-button>
    <AddBasketModal :edit="true" :basket="basket" />
  </div>
</template>

<script>
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { removeBasket } from '@/api/baskets'
import AddBasketModal from '@/views/partials/Modals/AddBasketModal.vue'
import { useBasketStore } from '@/stores/baskets'

export default {
  components: { AddBasketModal },
  props: {
    basket: { type: Object, required: true },
    mayEdit: { type: Boolean, default: false },
  },
  setup () {
    return {
      confirm: useConfirmationDialogue(),
      basketStore: useBasketStore(),
    }
  },
  methods: {
    async deleteBasket () {
      if (!await this.confirm.confirmationDialogue('basket.delete_confirmation.text')) return
      await removeBasket(this.basket.id)
      await this.basketStore.fetchOwn(true)
      location.href = this.$url('baskets')
    },
  },
}
</script>

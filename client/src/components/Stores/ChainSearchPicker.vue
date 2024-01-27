<template>
  <b-modal
    id="modal_open_chain_search_picker"
    ref="modal_open_chain_search_picker"
    :title="$i18n('store.chain_search_picker.modal_title')"
    hide-footer
    header-class="d-flex"
    content-class="pr-3 pt-3"
    size="xl"
    scrollable
  >
    <b-form-input
      v-model="filterName"
      :placeholder="$i18n('store.chain_search_picker.search_input')"
      class="mb-2"
    />
    <b-list-group>
      <b-list-group-item
        v-for="filteredStoreChain in filteredStoreChains"
        :key="filteredStoreChain.value"
        class="pb-2"
        href="#"
        @click="emitSelectedChain(filteredStoreChain)"
      >
        <b>{{ filteredStoreChain.text }}</b>
      </b-list-group-item>
    </b-list-group>
  </b-modal>
</template>

<script>
export default {
  props: {
    storeChains: { type: Array, default: () => [] },
  },
  data () {
    return {
      filterName: null,
    }
  },
  computed: {
    filteredStoreChains () {
      if (this.filterName === null) {
        return this.storeChains
      }
      return this.storeChains.filter(chain => {
        return chain.text.toLowerCase().includes(this.filterName.toLowerCase())
      })
    },
  },
  methods: {
    show () {
      this.$refs.modal_open_chain_search_picker.show()
    },
    emitSelectedChain (filteredStoreChain) {
      this.$emit('store-chain-selected', filteredStoreChain)
      this.$refs.modal_open_chain_search_picker.hide()
    },
  },
}
</script>

<style lang="scss" scoped>
.selected-item {
  background-color: var(--fs-color-secondary-400);
}
</style>

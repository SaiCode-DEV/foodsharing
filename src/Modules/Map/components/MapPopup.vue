<template>
  <b-modal
    :id="id"
    :ref="id"
    scrollable
    centered
  >
    <template #modal-header="{ close }">
      <slot v-if="!isLoading" name="popup-header" />
      <h3 v-else class="d-block w-100">
        <b-skeleton width="80%" />
      </h3>
      <button
        type="button"
        class="btn btn-sm no-shadow"
        @click="close"
      >
        <i class="fas fa-xmark" />
      </button>
    </template>

    <div
      v-if="isLoading"
      class="loader-container mx-auto"
    >
      <i class="fas fa-spinner fa-spin" />
    </div>
    <div v-else>
      <slot />
    </div>

    <template #modal-footer="{ hide }">
      <b-button
        v-if="showFooterCloseButton"
        variant="primary"
        @click="hide('forget')"
      >
        {{ $i18n('globals.close') }}
      </b-button>
      <slot v-if="!isLoading" name="popup-footer" />
    </template>
  </b-modal>
</template>

<script>
export default {
  props: {
    id: { type: String, required: true },
    isLoading: { type: Boolean, default: false },
    showFooterCloseButton: { type: Boolean, default: true },
  },
}
</script>

<style scoped lang="scss">
</style>

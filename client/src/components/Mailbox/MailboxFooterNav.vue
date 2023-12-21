<template>
  <div class="border p-2">
    <a
      href="#"
      class="d-flex justify-content-center align-items-center"
      @click="showEmailList"
    >{{ $i18n('back') }}</a>
  </div>
</template>

<script>
import { store, MAILBOX_PAGE } from '@/stores/mailbox'

export default {
  computed: {
    page () {
      return store.state.page
    },
  },
  methods: {
    showEmailList () {
      store.setPage(MAILBOX_PAGE.EMAIL_LIST)
    },
  },
}
</script>

<style scoped>

</style>

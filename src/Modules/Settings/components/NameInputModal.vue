<template>
  <b-modal
    ref="nameChangeInfoModal"
    :title="$i18n('settings.name_change.request')"
    ok-only
    centered
    modal-class="bootstrap"
    content-class="pr-3 pt-3"
    header-class="d-flex"
  >
    <Markdown :source="$i18n('settings.name_change.foodsaver_info', { link: $url('region_forum', regionId )})" />
  </b-modal>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  name: 'NameInputModal',
  components: { Markdown },
  props: {
    regionId: { type: Number, required: true },
  },
  methods: {
    show () {
      this.$refs.nameChangeInfoModal.show()
    },
  },
}
</script>

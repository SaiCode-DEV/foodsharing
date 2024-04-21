
<template>
  <div class="input-wrapper">
    <label
      class="wrapper-label ui-widget"
    >{{ $i18n('settings.name_change.title') }}</label>
    <div
      v-b-modal.name-change-info-modal
      class="alert alert-light border mb-2"
    >
      <Markdown :source="$i18n('settings.name_change.desc', {link:'#'})" />
    </div>
    <div class="row container m-0 p-0 ">
      <input
        id="name"
        class="form-control col mr-2"
        type="text"
        name="name"
        :value="name"
        disabled="disabled"
      >
      <input
        id="nachname"
        class="form-control col"
        type="text"
        name="nachname"
        :value="lastName"
        disabled="disabled"
      >
    </div>
    <div class="clear" />
    <b-modal
      id="name-change-info-modal"
      :title="$i18n('settings.name_change.request')"
      ok-only
      centered
      modal-class="bootstrap"
      content-class="pr-3 pt-3"
      header-class="d-flex"
    >
      <div
        v-if="regionId > 0"
        v-text="$i18n('settings.name_change.foodsaver_info', { link: $url('region_forum', regionId)})"
      />
      <div
        v-else
        v-text="$i18n('settings.name_change.foodsharer_info', { link: `${$url('freshdesk')}` })"
      />
    </b-modal>
  </div>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    name: {
      type: String,
      default: '',
    },
    lastName: {
      type: String,
      default: '',
    },
    regionId: {
      type: Number,
      default: 0,
    },
  },

}
</script>

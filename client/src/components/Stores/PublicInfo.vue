<template>
  <b-form-group
    :description="$i18n('storeview.visible_for_public')"
    :label="$i18n('storeview.public_info.label')"
    label-for="publicInfo"
    class="my-3"
  >
    <MarkdownInput
      ref="md-input"
      variant="outline-primary"
      :rows="2"
      :conceal-toolbar="true"
      :value="publicInfoData"
      :state="publicInfoState"
      :disabled="disabled"
      @update:value="newValue => updatePublicInfo(newValue)"
    />
    <span>{{ $i18n('storeview.public_info.available_count') }}: {{ MAX_LEN_FOR_PUBLIC_INFO() - publicInfoData.length }}</span>
    <span v-if="!publicInfoState" class="text-danger float-right">{{ $i18n('storeview.public_info.error_message', {count: MAX_LEN_FOR_PUBLIC_INFO()}) }}</span>
  </b-form-group>
</template>

<script>
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import { MAX_LEN_FOR_PUBLIC_INFO } from '@/stores/stores'

export default {
  components: { MarkdownInput },
  props: {
    publicInfo: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
  },
  data () {
    return {
      publicInfoData: this.publicInfo,
    }
  },
  computed: {
    publicInfoState () {
      if (this.publicInfoData === null || this.publicInfoData.length === 0) return true
      else return this.publicInfoData.length <= MAX_LEN_FOR_PUBLIC_INFO
    },
  },
  methods: {
    updatePublicInfo (newValue) {
      this.publicInfoData = newValue
      this.$emit('update:public-info', {
        publicInfo: newValue,
        publicInfoState: this.publicInfoState,
      })
    },
    MAX_LEN_FOR_PUBLIC_INFO () {
      return MAX_LEN_FOR_PUBLIC_INFO
    },
  },
}
</script>

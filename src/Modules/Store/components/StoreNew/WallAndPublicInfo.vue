<template>
  <div class="list-group-item">
    <MarkdownInput
      ref="md-input"
      :placeholder="$t('wall.placeholder')"
      :rows="2"
      :conceal-toolbar="true"
      :value="firstPost"
      @update:value="newValue => firstPost = newValue"
    />
    <PublicInfo @update:public-info="updatePublicInfo" />
    <div class="float-right">
      <button
        class="btn btn-primary ml-3 mt-3"
        type="button"
        @click="$emit('prev')"
      >
        {{ $t('button.prev') }}
      </button>
      <button
        class="btn btn-primary ml-3 mt-3"
        type="submit"
        :disabled="!publicInfoState"
        @click.prevent="redirect()"
      >
        {{ $t('button.next') }}
      </button>
    </div>
  </div>
</template>

<script>
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import PublicInfo from '@/components/Stores/PublicInfo.vue'

export default {
  components: { PublicInfo, MarkdownInput },
  data () {
    return {
      name: null,
      editMode: true,
      publicInfo: '',
      firstPost: '',
      publicInfoState: true,
    }
  },
  methods: {
    updatePublicInfo ({ publicInfo, publicInfoState }) {
      this.publicInfo = publicInfo
      this.publicInfoState = publicInfoState
    },
    redirect () {
      this.$emit('update:firstPost', this.firstPost)
      this.$emit('update:publicInfo', this.publicInfo)
      this.$emit('next')
    },
  },
}
</script>

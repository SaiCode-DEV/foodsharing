<template>
  <div class="list-group-item">
    <MarkdownInput
      ref="md-input"
      variant="outline-primary"
      :placeholder="$i18n('wall.placeholder')"
      :rows="2"
      :conceal-toolbar="true"
      :value="firstPost"
      @update:value="newValue => firstPost = newValue"
    />
    <b-form-group
      :description="$i18n('storeview.visible_for_public')"
      :label="$i18n('public_info')"
      label-for="publicInfo"
      class="my-3"
    >
      <b-form-textarea
        id="publicInfo"
        v-model="publicInfo"
        :state="publicInfoState"
        rows="5"
        max-rows="10"
        :disabled="!editMode"
      />
    </b-form-group>
    <div class="float-right">
      <button
        class="btn btn-primary ml-3 mt-3"
        type="button"
        @click="$emit('prev')"
      >
        {{ $i18n('button.prev') }}
      </button>
      <button
        class="btn btn-primary ml-3 mt-3"
        type="submit"
        @click.prevent="redirect()"
      >
        {{ $i18n('button.next') }}
      </button>
    </div>
  </div>
</template>

<script>
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import { MAX_LEN_FOR_PUBLIC_INFO } from '@/stores/stores'

export default {
  components: { MarkdownInput },
  data () {
    return {
      name: null,
      editMode: true,
      publicInfo: '',
      firstPost: '',
    }
  },
  computed: {
    publicInfoState () {
      if (!this.editMode) return null
      else return this.publicInfo.length <= MAX_LEN_FOR_PUBLIC_INFO
    },
  },
  methods: {
    redirect () {
      this.$emit('update:firstPost', this.firstPost)
      this.$emit('update:publicInfo', this.publicInfo)
      this.$emit('next')
    },
  },
}
</script>

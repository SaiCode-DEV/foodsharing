<template>
  <div>
    <ReactionsBar
      :reactions="reactions"
      @reaction-add="key => $emit('reaction-add', key)"
      @reaction-remove="key => $emit('reaction-remove', key)"
    />

    <span
      v-if="mayReply || mayDelete"
      class="divider text-black-50 mx-1"
    />
    <a
      v-if="mayReply"
      class="btn btn-sm btn-primary"
      :title="$i18n('thread.post.quote_button')"
      @click="event => event.shiftKey ? $emit('reply-full') : $emit('reply')"
    >
      {{ $i18n('button.answer') }}
    </a>
    <a
      v-if="mayHide"
      v-b-tooltip="$i18n('forum.post.hide')"
      class="btn btn-sm btn-danger"
      @click="$refs.hideModal.show()"
    >
      <i class="fas fa-eye-slash" />
    </a>
    <a
      v-if="mayDelete"
      v-b-tooltip.hover
      :title="$i18n('forum.post.delete')"
      class="btn btn-sm btn-danger"
      @click="$refs.confirmDelete.show()"
    >
      <i class="fas fa-trash-alt" />
    </a>

    <b-modal
      v-if="mayDelete"
      ref="confirmDelete"
      :title="$i18n('forum.post.delete')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      ok-variant="outline-danger"
      centered
      @ok="$emit('delete')"
    >
      <p>{{ $i18n('really_delete') }}</p>
    </b-modal>

    <b-modal
      v-if="mayHide"
      ref="hideModal"
      :title="$i18n('forum.post.sureHide')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      ok-variant="outline-danger"
      centered
      :ok-disabled="!hideReason"
      @ok="$emit('hide', hideReason)"
    >
      <b-form-group :label="$i18n('forum.post.giveHideReason')">
        <b-form-textarea
          v-model="hideReason"
          :state="hideReason ? null : false"
          :placeholder="$i18n('forum.post.hideReasonPlaceholder')"
          :maxlength="255"
        />
      </b-form-group>

      <b-alert show>
        <i class="fas fa-info-circle" />
        {{ $i18n('forum.post.hideInfo') }}
      </b-alert>
    </b-modal>
  </div>
</template>

<script>
import ReactionsBar from '@/components/Wall/ReactionsBar.vue'

export default {
  components: { ReactionsBar },
  props: {
    reactions: { type: [Object, Array], default: () => {} },
    mayDelete: { type: Boolean, default: false },
    mayHide: { type: Boolean, default: false },
    /**
     * Whether the user can write a reply. This is disabled in closed threads.
     */
    mayReply: { type: Boolean, default: true },
  },
  data () {
    return {
      hideReason: '',
    }
  },
}
</script>
<style lang="scss" scoped>
.divider::before {
  content: '|';
}
</style>

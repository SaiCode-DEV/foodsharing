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
      :title="$t('thread.post.quote_button')"
      @click="event => event.shiftKey ? $emit('reply-full') : $emit('reply')"
    >
      {{ $t('button.answer') }}
    </a>
    <a
      v-if="mayHide"
      v-b-tooltip="$t('forum.post.hide')"
      class="btn btn-sm btn-danger"
      @click="$refs.hideModal.show()"
    >
      <i class="fas fa-eye-slash" />
    </a>
    <a
      v-if="mayEdit"
      v-b-tooltip.hover
      :title="$t('forum.post.edit')"
      class="btn btn-sm btn-primary ml-2"
      @click="$emit('edit')"
    >
      <i class="fas fa-edit" />
    </a>
    <a
      v-if="mayDelete"
      v-b-tooltip.hover
      :title="$t('forum.post.delete')"
      class="btn btn-sm btn-danger"
      @click="$refs.confirmDelete.show()"
    >
      <i class="fas fa-trash-alt" />
    </a>

    <b-modal
      v-if="mayDelete"
      ref="confirmDelete"
      :title="$t('forum.post.delete')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.yes_i_am_sure')"
      ok-variant="outline-danger"
      centered
      @ok="$emit('delete')"
    >
      <p>{{ $t('really_delete') }}</p>
    </b-modal>

    <b-modal
      v-if="mayHide"
      ref="hideModal"
      :title="$t('forum.post.sureHide')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.yes_i_am_sure')"
      ok-variant="outline-danger"
      centered
      :ok-disabled="!hideReason"
      @ok="$emit('hide', hideReason)"
    >
      <b-form-group :label="$t('forum.post.giveHideReason')">
        <b-form-textarea
          v-model="hideReason"
          :state="hideReason ? null : false"
          :placeholder="$t('forum.post.hideReasonPlaceholder')"
          :maxlength="255"
        />
      </b-form-group>

      <b-alert show>
        <i class="fas fa-info-circle" />
        {{ $t('forum.post.hideInfo') }}
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
    mayEdit: { type: Boolean, default: false },
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

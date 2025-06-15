<template>
  <div :id="`post-${post.id}`" class="thread">
    <div
      ref="card"
      class="card mb-2 post-card"
      :class="{'disabledLoading': isLoading, 'hidden': post.hidden}"
    >
      <div class="card-header d-flex align-items-center justify-content-between">
        <Avatar
          v-if="wXS || post.hidden"
          :user="post.author"
          class="mr-2"
        />
        <span class="flex-grow-1">
          <i
            v-if="isLinked"
            v-b-tooltip="$i18n('thread.post.linked_post')"
            class="fas fa-link mr-1"
          />
          <a :href="$url('profile', post.author.id)">
            <strong class="author">{{ post.author.name }}</strong>
          </a><!--
       --><template v-if="post.hidden">:
            <Markdown
              class="d-inline-block"
              :source="$i18n('forum.post.hiddenPost', {
                moderatorName: post.hidden.moderator.name,
                moderatorUrl: $url('profile', post.hidden.moderator.id),
              })"
            />
            ({{ $i18n('forum.post.hiddenReason', post.hidden) }}
            <Time :time="post.hidden.time" />)
          </template>
        </span>
        <Time
          :time="post.createdAt"
          class="text-right"
        />
        <OverflowMenu :options="overflowMenuOptions" />
      </div>
      <div v-if="!post.hidden" class="d-flex m-2">
        <div
          v-if="!wXS"
          class="mr-2 pr-2 border-right border-light text-center"
          style="width: min-content; min-width: 150px;"
        >
          <Avatar
            :user="post.author"
            class="mb-2"
            :size="130"
          />
          <a
            v-if="!wXS && !isMe"
            class="btn btn-sm btn-outline-primary"
            @click="openChat"
          >
            <i class="fas fa-fw fa-comments" />
            {{ $i18n('chat.open_chat') }}
          </a>
        </div>
        <div class="body m-2 mr-md-5 text-break flex-shrink-fix">
          <Markdown :source="post.body" />
        </div>
      </div>
      <div v-if="!post.hidden" class="card-footer text-right">
        <ThreadPostActions
          :reactions="post.reactions"
          :may-delete="post.mayDelete"
          :may-reply="mayReply"
          :may-hide="mayHide"
          @delete="$emit('delete')"
          @hide="$emit('hide', $event)"
          @reaction-add="key => $emit('reaction-add', key)"
          @reaction-remove="key => $emit('reaction-remove', key)"
          @reply="$emit('reply', post.body)"
          @reply-full="$emit('reply-full', post.body)"
        />
      </div>
    </div>
    <b-modal
      v-if="mayModerate && post.hidden"
      ref="restoreModal"
      :title="$i18n('forum.restore.title')"
      centered
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      @ok="restore"
    >
      {{ $i18n('forum.restore.really') }}
      <blockquote>
        <Markdown :source="post.body" />
      </blockquote>
      <ul>
        <li>
          {{ $i18n('forum.restore.hidden_by') }}
          <a :href="$url('profile', post.hidden.moderator.id)" v-text="post.hidden.moderator.name" />
        </li>
        <li v-text="$i18n('forum.restore.reason', { reason: post.hidden.reason })" />
        <li>
          <Time
            :time="post.hidden.time"
            normal-size
            :muted="false"
          />
        </li>
      </ul>
    </b-modal>
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import ThreadPostActions from './ThreadPostActions'
import conversationStore from '@/stores/conversations'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin'
import Markdown from '@/components/Markdown/Markdown.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import Time from '@/components/Time.vue'
import { pulseSuccess } from '@/script'

export default {
  components: { Avatar, ThreadPostActions, Markdown, OverflowMenu, Time },
  mixins: [MediaQueryMixin, CopyToClipboardMixin],
  props: {
    post: { type: Object, required: true },
    userId: { type: Number, required: true },
    deepLink: { type: String, default: '' },
    reactions: { type: Object, default: () => ({}) },
    mayHide: { type: Boolean, default: false },
    mayModerate: { type: Boolean, default: false },
    isLoading: { type: Boolean, default: true },
    mayReply: { type: Boolean, default: true },
    isLinked: { type: Boolean, default: false },
  },
  data: () => ({
    hiddenDetails: null,
  }),
  computed: {
    isMe () {
      return this.userId === this.post.author.id
    },
    overflowMenuOptions () {
      return [
        { hide: !navigator.clipboard || this.post.hidden, icon: 'copy', textKey: 'thread.post.options.copy_source', callback: this.copySourceCodeToClipboard },
        { icon: 'chain', textKey: 'thread.post.options.copy_direct_link', callback: this.copyDirectLink },
        { hide: !this.post.hidden || !this.mayModerate, icon: 'eye', textKey: 'thread.post.options.restore', callback: this.showRestoreModal },
      ]
    },
  },
  methods: {
    openChat () {
      conversationStore.openChatWithUser(this.post.author.id)
    },
    async copySourceCodeToClipboard () {
      await navigator.clipboard.writeText(this.post.body)
      pulseSuccess(this.$i18n('thread.post.copy_source_success'))
    },
    async copyDirectLink () {
      this.copyToClipboard(location.protocol + '//' + location.host + this.deepLink, 'thread.post.copy_direct_link_success')
    },
    async restore () {
      this.$emit('restore')
    },
    async showRestoreModal () {
      this.$refs.restoreModal.show()
    },
  },
}
</script>
<style lang="scss" scoped>
.post-card {
  border: 1px solid var(--fs-border-default);
}
.hidden {
  border: 1px solid var(--fs-color-danger-400);
  background-color: var(--fs-color-danger-200);
}
</style>

<template>
  <div :id="`post-${id}`" class="thread">
    <div
      ref="card"
      class="card mb-2"
      :class="{'disabledLoading': isLoading}"
    >
      <div class="card-header d-flex align-items-center justify-content-between">
        <Avatar
          v-if="wXS"
          :user="author"
          class="mr-2"
        />
        <span class="flex-grow-1">
          <i
            v-if="isLinked"
            v-b-tooltip="$i18n('thread.post.linked_post')"
            class="fas fa-link mr-1"
          />
          <a :href="$url('profile', author.id)">
            <strong class="author">{{ author.name }}</strong>
          </a>
        </span>
        <Time
          :time="createdAt"
          class="text-right"
        />
        <OverflowMenu :options="overflowMenuOptions" />
      </div>
      <div class="d-flex m-2">
        <div
          v-if="!wXS"
          class="mr-2 pr-2 border-right border-light text-center"
          style="width: min-content; min-width: 150px;"
        >
          <Avatar
            :user="author"
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
        <div class="body m-2 mr-md-5 text-break">
          <Markdown :source="body" />
        </div>
      </div>
      <div class="card-footer text-right">
        <ThreadPostActions
          :reactions="reactions"
          :may-delete="mayDelete"
          :may-edit="mayEdit"
          :may-reply="mayReply"
          @delete="$emit('delete')"
          @reaction-add="$emit('reaction-add', $event)"
          @reaction-remove="$emit('reaction-remove', $event)"
          @reply="$emit('reply', body)"
        />
      </div>
    </div>
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import ThreadPostActions from './ThreadPostActions'
import conversationStore from '@/stores/conversations'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import Markdown from '@/components/Markdown/Markdown.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import Time from '@/components/Time.vue'
import { pulseSuccess } from '@/script'

export default {
  components: { Avatar, ThreadPostActions, Markdown, OverflowMenu, Time },
  mixins: [MediaQueryMixin],
  props: {
    id: { type: Number, default: null },
    userId: { type: Number, required: true },
    body: { type: String, default: '' },
    author: { type: Object, default: () => ({ avatar: null }) },
    createdAt: { type: Date, default: null },
    deepLink: { type: String, default: '' },
    reactions: { type: Object, default: () => ({}) },
    mayEdit: { type: Boolean, default: false },
    mayDelete: { type: Boolean, default: false },
    isLoading: { type: Boolean, default: true },
    mayReply: { type: Boolean, default: true },
    isLinked: { type: Boolean, default: false },
  },
  computed: {
    isMe () {
      return this.userId === this.author.id
    },
    overflowMenuOptions () {
      return [
        { hide: !navigator.clipboard, icon: 'copy', textKey: 'thread.post.options.copy_source', callback: this.copySourceCodeToClipboard },
        { icon: 'chain', textKey: 'thread.post.options.copy_direct_link', callback: this.copyDirectLink },
      ]
    },
  },
  methods: {
    openChat () {
      conversationStore.openChatWithUser(this.author.id)
    },
    async copySourceCodeToClipboard () {
      await navigator.clipboard.writeText(this.body)
      pulseSuccess(this.$i18n('thread.post.copy_source_success'))
    },
    async copyDirectLink () {
      await navigator.clipboard.writeText(location.host + this.deepLink)
      pulseSuccess(this.$i18n('thread.post.copy_direct_link_success'))
    },
  },
}
</script>

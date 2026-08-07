<template>
  <div
    :id="`wallpost-${post.id}`"
    class="list-group-item d-flex"
    style="gap: 1em"
  >
    <Avatar :user="post.author" :size="50" />
    <div class="flex-grow-1 position-relative flex-shrink-fix">
      <div class="d-flex" style="gap: 0.5em">
        <a :href="$url('profile', post.author.id)" v-text="post.author.name" />
        <span class="flex-grow-1" />
        <TimeDisplay :time="post.time" />
        <OverflowMenu
          :options="menuOptions"
          style="margin: -0.6em -0.5em -0.3em -0.5em"
        />
      </div>
      <Markdown :source="post.body" />

      <Gallery
        :images="post.pictures"
        :height-in-px="galleryHeightInPx"
        :class="{ 'mb-2': mayReact }"
      />
      <ReactionsBar
        v-if="mayReact"
        class="float-right"
        :reactions="post.reactions"
        unobtrusive
        @reaction-add="key => $emit('reaction-add', key)"
        @reaction-remove="key => $emit('reaction-remove', key)"
      />
    </div>
  </div>
</template>

<script>
import { useUserStore } from '@/stores/user'
import Avatar from '@/components/Avatar/Avatar.vue'
import Markdown from '@/components/Markdown/Markdown'
import Gallery from '@/components/Images/Gallery'
import TimeDisplay from '@/components/TimeDisplay.vue'
import ReactionsBar from './ReactionsBar.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin'

export default {
  components: { Avatar, Markdown, Gallery, TimeDisplay, ReactionsBar, OverflowMenu },
  mixins: [CopyToClipboardMixin],
  props: {
    post: { type: Object, required: true },
    mayDeleteEverything: { type: Boolean, default: false },
    mayReact: { type: Boolean, default: false },
    galleryHeightInPx: { type: Number, default: undefined },
    target: { type: String, default: null },
  },
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  computed: {
    canDelete () {
      return this.mayDeleteEverything || this.post.author.id === this.userStore.getUserId
    },
    menuOptions () {
      return [
        { hide: !navigator.clipboard, icon: 'copy', textKey: 'thread.post.options.copy_source', callback: this.copySourceCodeToClipboard },
        { icon: 'chain', textKey: 'thread.post.options.copy_direct_link', callback: this.copyDirectLink },
        { hide: !this.canDelete, textKey: 'wall.delete', icon: 'trash-alt', callback: () => this.$emit('delete', this.post.id) },
      ]
    },
  },
  methods: {
    async copySourceCodeToClipboard () {
      this.copyToClipboard(this.post.body, 'thread.post.copy_source_success')
    },
    async copyDirectLink () {
      this.copyToClipboard(location.protocol + '//' + location.host + location.pathname + `?showPost=${this.target}-${this.post.id}`, 'thread.post.copy_direct_link_success')
    },
  },
}
</script>

<style lang="scss" scoped>
.delete-post:hover {
  color: var(--fs-color-danger-500) !important;
  cursor: pointer;
}

.preview-images {
  white-space: nowrap;
  overflow-x: scroll;
  position: relative;
  width: 0px;
  min-width: 100%;
  margin-top: 1em;
  padding: .5em;
  box-shadow: 0 0 4px 2px #0002 inset;
  border-radius: var(--border-radius);
  text-align: center;
  background-color: var(--fs-color-gray-100);
  > :last-child {
    margin-right: 0.75em;
  }
}

::v-deep.b-avatar {
  height: fit-content;
  position: sticky;
  top: calc(var(--navbar-height) + 1em);
}

.unobtrusive-reactions {
  color: red;
}
</style>

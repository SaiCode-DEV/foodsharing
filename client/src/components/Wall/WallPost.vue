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
        <Time :time="post.time" />
        <i
          v-if="canDelete"
          v-b-tooltip="$i18n('wall.delete')"
          class="fas fa-trash-alt text-muted delete-post"
          @click="$emit('delete', post.id)"
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
import Time from '../Time.vue'
import ReactionsBar from './ReactionsBar.vue'

export default {
  components: { Avatar, Markdown, Gallery, Time, ReactionsBar },
  props: {
    post: { type: Object, required: true },
    mayDeleteEverything: { type: Boolean, default: false },
    mayReact: { type: Boolean, default: false },
    galleryHeightInPx: { type: Number, default: undefined },
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

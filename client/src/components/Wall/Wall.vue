<template>
  <!-- TODO create a way to restrict access to image sending to certain group -->
  <Container
    v-if="(posts.length || mayPost) && loaded"
    ref="wallContainer"
    :title="title ?? $t('wall.name')"
    :tag="`wall-${target}-${targetId}`"
    :hide-header="hideHeader"
  >
    <div v-if="mayPost" class="list-group-item">
      <MarkdownInput
        ref="md-input"
        variant="outline-primary"
        :placeholder="$t('wall.placeholder')"
        :rows="2"
        :conceal-toolbar="true"
        :value="newPostText"
        :allow-image-attachments="allowImageAttachments"
        :draft-storage-id="'wall-new-post-' + target + '-' + targetId"
        @update:value="newValue => newPostText = newValue"
        @submit="writePost"
        @image-change="newValue => hasImages = newValue"
      />

      <div class="submit d-flex">
        <b-button
          class="ml-auto mt-2"
          :class="{ 'd-none': !newPostExists }"
          variant="outline-secondary"
          :disabled="!newPostExists"
          @click.prevent.stop="writePost"
        >
          {{ $t('button.send') }}
        </b-button>
      </div>
    </div>

    <div v-if="loading.sendPost" class="list-group-item d-flex">
      <b-skeleton size="50px" class="mr-3" />
      <div class="flex-grow-1">
        <b-skeleton width="85%" />
        <b-skeleton width="55%" />
      </div>
    </div>
    <WallPost
      v-for="p in posts"
      :key="p.id"
      :post="p"
      :target="target"
      :may-delete-everything="mayDeleteEverything"
      :may-react="mayReact"
      :gallery-height-in-px="galleryHeightInPx"
      class="wallpost"
      @delete="deletePost"
      @reaction-add="key => addReaction(p.id, key)"
      @reaction-remove="key => removeReaction(p.id, key)"
    />

    <ContainerButton
      v-if="showLoadMore && !loading.morePosts"
      variant="success"
      text-key="globals.show_more"
      @click="loadMorePosts"
    />
    <ContainerButton v-if="loading.morePosts" variant="warning">
      <i class="fas fa-spinner fa-spin" />
    </ContainerButton>
  </Container>
</template>

<script>
import WallPost from './WallPost'
import { showLoader, hideLoader, pulseError, pulseWarning } from '@/script'
import Container from '@/components/Container/Container.vue'
import MarkdownInput from '../Markdown/MarkdownInput.vue'
import { getWallPosts, addPost, deletePost, addReaction, removeReaction } from '@/api/wall'
import { HTTP_RESPONSE } from '@/consts'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { sameRouteNavigationEvent } from '@/helper/router'

export default {
  components: { WallPost, Container, MarkdownInput, ContainerButton },
  props: {
    targetId: { type: Number, required: true },
    target: { type: String, required: true },
    title: { type: String, default: null },
    hideHeader: { type: Boolean, default: false },
    galleryHeightInPx: { type: Number, default: undefined },
    pageSize: { type: Number, default: 10 },
    firstPageSize: { type: Number, default: undefined },
    loaded: { type: Boolean, default: true },
    allowImageAttachments: { type: Boolean, default: true },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data () {
    return {
      posts: [],
      mayPost: false,
      mayDeleteEverything: false,
      mayReact: false,
      newPostText: '',
      hasImages: false,
      showLoadMore: true,
      // Post id from a deep link (e.g. `?showPost=store-123` in a notification URL), or null.
      linkedPostId: null,
      loading: {
        morePosts: true,
        sendPost: false,
      },
    }
  },
  computed: {
    newPostExists () {
      return this.newPostText.trim().length > 0 || this.hasImages
    },
  },
  watch: {
    // Client side navigation can leave this component mounted while only the `showPost`
    // query parameter changes (e.g. clicking a link to a post of a wall that is already
    // open), so the deep link also has to be re-evaluated reactively instead of only
    // once in created().
    '$route.query.showPost' () {
      this.showLinkedPost()
    },
  },
  async created () {
    // Clicking the link to the post that is already linked in the url does not change
    // the route, so the watcher above cannot pick it up.
    window.addEventListener(sameRouteNavigationEvent, this.onSameRouteNavigation)
    this.parseDeepLink()
    await this.loadMorePosts()
    await this.openLinkedPost()
  },
  beforeDestroy () {
    window.removeEventListener(sameRouteNavigationEvent, this.onSameRouteNavigation)
  },
  methods: {
    parseDeepLink () {
      // Notification deep link: `?showPost=<wallType>-<postId>` (e.g. `?showPost=store-123`).
      // The wall type prefix keeps it unambiguous when several walls share a page —
      // only react when the type matches this wall's target.
      const showPost = this.$route.query.showPost
      const match = showPost?.match(/^([a-z_]+)-(\d+)$/)
      this.linkedPostId = (match && match[1] === this.target) ? parseInt(match[2], 10) : null
    },
    onSameRouteNavigation () {
      // The url did not change, but the wall may have grown since it was loaded, so a
      // repeated click on the same link has to refetch it.
      this.showLinkedPost(true)
    },
    // A deep link that arrives (or is clicked again) while the wall is already loaded.
    async showLinkedPost (forceReload = false) {
      this.parseDeepLink()
      if (this.linkedPostId === null) return
      if (forceReload || !this.posts.some(post => post.id === this.linkedPostId)) {
        // The linked post can be newer than everything loaded so far, i.e. it was written
        // after the wall was loaded (e.g. a bell for a new post on the page that is
        // already open). Then the loaded posts are stale and have to be fetched again.
        if (forceReload || !this.posts.length || this.linkedPostId > this.posts[0].id) {
          this.posts = []
          this.showLoadMore = true
        }
        // Otherwise it is older than the loaded posts and the wall only has to be
        // extended down to it (the api expands the limit to include the anchor).
        if (this.showLoadMore) {
          await this.loadMorePosts()
        }
      }
      await this.openLinkedPost()
    },
    // When opened via a deep link (e.g. from a notification), make sure the wall is
    // expanded and scroll the linked post into view — even if it was collapsed before.
    async openLinkedPost () {
      if (this.linkedPostId === null) return
      if (!this.posts.some(post => post.id === this.linkedPostId)) {
        pulseWarning(this.$t('wall.post_not_found'))
        return
      }
      await this.$nextTick()
      this.$refs.wallContainer?.expand()
      await this.$nextTick()
      const el = document.getElementById(`wallpost-${this.linkedPostId}`)
      if (!el) return
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
      await new Promise(resolve => window.setTimeout(resolve, 500))
      el.animate({
        backgroundColor: ['transparent', 'var(--fs-color-warning-alpha-60)', 'transparent'],
        offset: [0, 0.05, 1],
        easing: ['ease-out', 'ease-in'],
      }, {
        direction: 'alternate',
        duration: 4000,
        iterations: 1,
      })
    },
    async loadMorePosts () {
      this.loading.morePosts = true
      this.page++
      const limit = (!this.page && this.firstPageSize) ? this.firstPageSize : this.pageSize
      let data
      try {
        data = await getWallPosts(this.target, this.targetId, limit, this.posts.length, this.linkedPostId)
      } catch {
        return
      }

      // Filter out already loaded posts. This can happen if other users delete posts in the meantime.
      const postIds = new Set(this.posts.map(post => post.id))
      this.posts.push(...data.posts.filter(post => !postIds.has(post.id)))

      if (data.posts.length < limit) {
        this.showLoadMore = false
      }
      this.mayPost = data.mayPost
      this.mayDeleteEverything = data.mayDelete
      this.mayReact = data.mayReact
      this.loading.morePosts = false
    },
    async writePost () {
      const text = this.newPostText.trim()
      if (!(text || this.hasImages)) return
      try {
        this.loading.sendPost = true
        this.newPostText = ''
        let images
        if (this.hasImages) {
          images = await this.$refs['md-input'].uploadImages()
          this.$refs['md-input'].clearImages()
        }
        const newPost = await addPost(this.target, this.targetId, text, images)
        this.posts.unshift(newPost)
      } catch (e) {
        console.error(e)
        pulseError(this.$t('wall.error-create'))
        this.newPostText = text
      } finally {
        this.loading.sendPost = false
      }
    },
    async deletePost (postId) {
      const index = this.posts.findIndex(post => post.id === postId)
      const params = { author: this.posts[index].author.name }
      if (!await this.confirmationDialogue('wall.delete_confirmation.text', { params })) return
      try {
        showLoader()
        await deletePost(this.target, this.targetId, postId)
        if (index >= 0) this.posts.splice(index, 1)
      } catch (error) {
        if (error.code === HTTP_RESPONSE.FORBIDDEN) pulseError(this.$t('wall.error-delete'))
        else {
          pulseError(this.$t('error_unexpected'))
          console.error(error)
        }
      } finally {
        hideLoader()
      }
    },
    async addReaction (postId, key) {
      try {
        await addReaction(this.target, this.targetId, postId, key)
      } catch (error) {
        pulseError(this.$t('error_unexpected'))
        console.error(error)
      }
    },
    async removeReaction (postId, key) {
      try {
        await removeReaction(this.target, this.targetId, postId, key)
      } catch (error) {
        pulseError(this.$t('error_unexpected'))
        console.error(error)
      }
    },
  },
}
</script>

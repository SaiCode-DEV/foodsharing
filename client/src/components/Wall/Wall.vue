<!-- eslint-disable vue/no-v-model-argument -->
<template>
  <!-- TODO create a way to restrict access to image sending to certain group -->
  <Container
    v-if="filteredPosts?.length || mayPost"
    :title="title ?? $i18n('wall.name')"
    tag="store_wall"
    :toggle-visiblity="filteredPosts.length > defaultAmount"
    :hide-header="hideHeader"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <div v-if="mayPost" class="list-group-item">
      <MarkdownInput
        ref="md-input"
        variant="outline-primary"
        :placeholder="$i18n('wall.placeholder')"
        :rows="2"
        :conceal-toolbar="true"
        :value="newPostText"
        allow-image-attachments
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
          {{ $i18n('button.send') }}
        </b-button>
      </div>
    </div>

    <WallPost
      v-for="p in filteredList"
      :key="p.id"
      :post="p"
      :may-delete-everything="mayDeleteEverything"
      :gallery-height-in-px="galleryHeightInPx"
      class="wallpost"
      @delete="deletePost"
    />
  </Container>
</template>

<script>
import WallPost from './WallPost'
import { showLoader, hideLoader, pulseError } from '@/script'
import ListToggleMixin from '@/mixins/ContainerToggleMixin'
import Container from '@/components/Container/Container.vue'
import MarkdownInput from '../Markdown/MarkdownInput.vue'
import { getWallPosts, addPost, deletePost } from '@/api/wall'
import { HTTP_RESPONSE } from '@/consts'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

export default {
  components: { WallPost, Container, MarkdownInput },
  mixins: [ListToggleMixin, ConfirmationDialogue],
  props: {
    targetId: { type: Number, required: true },
    target: { type: String, required: true },
    title: { type: String, default: null },
    hideHeader: { type: Boolean, default: false },
    // excerptLength: { type: Number, default: 10 }, // how many entries are shown initially? Also the number of entries shown if "show less" is clicked
    // TODO for next followup: pagination for wall posts, similar to how the activity overview handles it.
    galleryHeightInPx: { type: Number, default: undefined },
  },
  data () {
    return {
      posts: undefined,
      mayPost: false,
      mayDeleteEverything: false,
      newPostText: '',
      isExcerptListExpanded: false,
      hasImages: false,
    }
  },
  computed: {
    filteredPosts () {
      this.setList(this.posts)
      return this.posts
    },
    newPostExists () {
      return this.newPostText.trim().length > 0 || this.hasImages
    },
    displayedPosts () {
      return (this.showOnlyExcerpt && !this.isExcerptListExpanded) ? (this.posts || []).slice(0, this.numberOfVisiblePostsPerExcerptIteration) : this.posts
    },
    hasMorePosts () {
      return (this.showOnlyExcerpt && !this.isExcerptListExpanded) ? (this.posts && this.posts.length > this.numberOfVisiblePostsPerExcerptIteration) : false
    },
  },
  async created () {
    const data = await getWallPosts(this.target, this.targetId)
    this.posts = data.posts
    this.mayPost = data.mayPost
    this.mayDeleteEverything = data.mayDelete
  },
  methods: {
    async writePost () {
      const text = this.newPostText.trim()
      if (!(text || this.hasImages)) return
      try {
        showLoader()
        this.newPostText = ''
        let images
        if (this.hasImages) {
          images = await this.$refs['md-input'].uploadImages()
          images = images.map(image => image.url)
          this.$refs['md-input'].clearImages()
        }
        const newPost = await addPost(this.target, this.targetId, text, images)
        this.posts.unshift(newPost)
      } catch (e) {
        console.error(e)
        pulseError(this.$i18n('wall.error-create'))
        this.newPostText = text
      } finally {
        hideLoader()
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
        if (error.code === HTTP_RESPONSE.FORBIDDEN) pulseError(this.$i18n('wall.error-delete'))
        else {
          pulseError(this.$i18n('error_unexpected'))
          console.error(error)
        }
      } finally {
        hideLoader()
      }
    },
  },
}
</script>

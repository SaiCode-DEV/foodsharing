<!-- eslint-disable vue/no-v-model-argument -->
<template>
  <!-- TODO create a way to restrict access to image sending to certain group -->
  <Container
    v-if="filteredPosts?.length || mayPost"
    :title="title ?? $i18n('wall.name')"
    tag="store_wall"
    :toggle-visiblity="filteredPosts.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <div
      v-if="mayPost"
      class="list-group-item"
    >
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
      :managers="managers"
      :may-delete-everything="mayDeleteEverything"
      :is-coordinator="isCoordinator"
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

export default {
  components: { WallPost, Container, MarkdownInput },
  mixins: [ListToggleMixin],
  props: {
    targetId: { type: Number, required: true },
    target: { type: String, required: true },
    showOnlyExcerpt: { type: Boolean, default: false },
    managers: { type: Array, default: () => [] },
    isCoordinator: { type: Boolean, default: false },
    numberOfVisiblePostsPerExcerptIteration: { type: Number, default: 3 },
    title: { type: String, default: null },
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
      const confimation = await this.$bvModal.msgBoxConfirm(this.$i18n('wall.delete_confirmation.text', { author: this.posts[index].author.name }), {
        title: this.$i18n('wall.delete_confirmation.title'),
        okVariant: 'danger',
        okTitle: this.$i18n('button.delete'),
        cancelTitle: this.$i18n('button.cancel'),
        centered: true,
      })
      if (!confimation) return
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

<template>
  <div class="blog-list-item d-flex align-items-center py-1 flex-wrap flex-sm-nowrap">
    <div class="mx-1">
      <b-link
        :disabled="!mayPublish"
        @click="togglePublished"
      >
        <i
          v-b-tooltip.hover="$t(isPublished ? 'blog.status.1' : 'blog.status.0')"
          class="fas fa-fw"
          :class="[isPublished ? 'fa-check-square text-secondary' : 'fa-eye-slash text-primary']"
        />
      </b-link>
    </div>
    <Avatar :user="author" />
    <div class="mx-1 blog-text">
      <span class="blog-title ml-1" v-text="blogTitle" />
      <span class="blog-teaser d-inline-block mx-1 text-muted" v-text="blogTeaser" />
    </div>
    <span class="flex-grow-1" />
    <Time :time="when" class="mr-2" />
    <b-link
      v-if="mayEdit"
      v-b-tooltip="$t('blog.edit')"
      class="ml-auto mx-1"
      :href="$url('blogEdit', blogId)"
    >
      <i class="fas fa-fw fa-pencil-alt" />
    </b-link>
    <b-button
      v-if="mayDelete"
      v-b-tooltip="$t('blog.delete')"
      href="#"
      size="sm"
      class="mx-1"
      variant="outline-danger"
      @click.prevent="removeBlogpost"
    >
      <i class="fas fa-fw fa-trash-alt" />
    </b-button>
  </div>
</template>

<script>
import { publishBlogpost, deleteBlogpost } from '@/api/blog'
import Avatar from '@/components/Avatar/Avatar.vue'
import Time from '@/components/Time.vue'
import i18n from '@/helper/i18n'
import { showLoader, hideLoader, pulseSuccess, pulseError } from '@/script'

export default {
  components: { Avatar, Time },
  props: {
    blogId: { type: Number, required: true },
    blogTitle: { type: String, default: '' },
    blogTeaser: { type: String, default: '' },
    published: { type: Boolean, required: true },
    createdAt: { type: String, required: true },
    author: { type: Object, required: true },
    lastEditorId: { type: Number, default: null },
    mayEdit: { type: Boolean, default: false },
  },
  data () {
    return {
      isPublished: this.published,
      when: new Date(Date.parse(this.createdAt.replace(' ', 'T'))),
      mayPublish: this.mayEdit,
      mayDelete: this.mayEdit,
    }
  },
  methods: {
    async togglePublished () {
      try {
        await publishBlogpost(this.blogId, !this.isPublished)
        this.isPublished = !this.isPublished
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
    },
    async removeBlogpost () {
      if (!confirm(i18n('blog.confirmDelete', { name: this.blogTitle }))) {
        return
      }
      showLoader()
      await deleteBlogpost(this.blogId)
      hideLoader()
      pulseSuccess(i18n('success'))
      this.$emit('remove-blogpost-from-list', this.blogId)
    },
  },
}
</script>

<style lang="scss" scoped>
.blog-list-item {
  &, div {
    font-size: 0.875rem;
  }
  .blog-teaser {
    font-size: 0.75rem;
  }
  @media only screen and (max-width: 30rem) {
    .blog-text {
      flex-basis: 100%;
      order: 1;
    }
  }
}
</style>

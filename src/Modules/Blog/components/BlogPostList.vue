<!-- Show a list of posts on the blog page and handles pagination -->
<template>
  <Container :title="$t('blog.header')" :collapsible="false">
    <div
      v-if="isLoading"
      class="loader-container mx-auto"
    >
      <i class="fas fa-spinner fa-spin" />
    </div>
    <div v-else>
      <blog-post-list-item
        v-for="blogPost in blogPosts[currentPage]"
        :key="blogPost.id"
        :blog-post="blogPost"
      />
    </div>

    <b-pagination
      v-model="currentPage"
      :total-rows="totalPosts"
      :per-page="10"
      class="pagination m-2"
      align="center"
    />
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import { getBlogposts } from '@/api/blog'
import { pulseError } from '@/script'
import BlogPostListItem from './BlogPostListItem'
import { BPagination } from 'bootstrap-vue'

export default {
  components: { Container, BlogPostListItem, BPagination },
  data () {
    return {
      isLoading: true,
      currentPage: 1,
      totalPosts: 10,
      blogPosts: {
        1: [],
      },
    }
  },
  watch: {
    currentPage () {
      // posts are buffered per page and only fetched if they are not buffered yet
      if (!this.blogPosts[this.currentPage]) {
        this.blogPosts[this.currentPage] = []
        this.loadCurrentPage()
      }
    },
  },
  mounted () {
    this.loadCurrentPage()
  },
  methods: {
    async loadCurrentPage () {
      this.isLoading = true

      try {
        const response = await getBlogposts(this.currentPage - 1)
        this.totalPosts = response.totalPosts
        this.blogPosts[this.currentPage] = response.blogPosts
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      }

      this.isLoading = false
    },
  },
}
</script>

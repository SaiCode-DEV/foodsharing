<template>
  <div class="blogpost ui-widget ui-widget-content">
    <div v-if="blogPost">
      <h2>{{ blogPost.title }}</h2>
      <p class="subtitle">
        <span v-if="blogPost.authorName">{{ $i18n('blog.author') }} {{ blogPost.authorName }}, </span>
        <span>{{ formattedDate }}</span>
      </p>
      <img v-if="pictureUrl" :src="pictureUrl">
      <!-- eslint-disable vue/no-v-html -->
      <!-- Sanitized in Modules/Blog/BlogGateway.php getPost() and getOne_blog_entry() -->
      <div v-html="blogPost.content" />
      <!-- eslint-enable -->
    </div>
    <div
      v-else
      class="loader-container mx-auto"
    >
      <i class="fas fa-spinner fa-spin" />
    </div>
  </div>
</template>

<script>
import { pulseError } from '@/script'
import { getBlogpost } from '@/api/blog'

export default {
  props: {
    id: { type: Number, required: true },
  },
  data () {
    return {
      blogPost: null,
    }
  },
  computed: {
    pictureUrl () {
      if (this.blogPost === null || this.blogPost.picture.length === 0) {
        return null
      }

      if (this.blogPost.picture.startsWith('/api/uploads/')) {
        return this.blogPost.picture // path for pictures uploaded with the new API
      } else {
        return '/images/' + this.blogPost.picture.replace('/', '/crop_0_528_') // backward compatible path for old pictures
      }
    },
    formattedDate () {
      return this.$dateFormatter.format(this.blogPost.publishedAt, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
      })
    },
  },
  async mounted () {
    try {
      // await new Promise(resolve => setTimeout(resolve, 5000))
      this.blogPost = await getBlogpost(this.id)
    } catch (e) {
      pulseError(this.$i18n('error_unexpected'))
    }
  },
}
</script>

<style scoped lang="scss">
.blogpost {
  border-radius: 6px;
  margin-bottom: 14px;
  padding: 20px;
  border-bottom: 1px solid var(--fs-border-default);

  img {
    border-radius: 6px;
    float: right;
    margin-right: 0;
    margin-left: 15px;
  }

  .subtitle {
    font-size: 80%;
  }
}

@media (max-width: 900px) {
  .blogpost img {
    width: 100% !important;
    float: none;
    margin-bottom: 15px;
    margin-left: 0;
  }
}
</style>

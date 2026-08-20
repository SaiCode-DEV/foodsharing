<template>
  <Container
    :title="$t('blog.header')"
    :collapsible="false"
    wrap-content
  >
    <div v-if="blogPost" class="blogpost">
      <h2>{{ blogPost.title }}</h2>
      <p class="subtitle">
        <span v-if="blogPost.authorName">{{ $t('blog.author') }} {{ blogPost.authorName }}, </span>
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
  </Container>
</template>

<script>
import { pulseError } from '@/script'
import { getBlogpost } from '@/api/blog'
import Container from '@/components/Container/Container.vue'

export default {
  components: { Container },
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
      return (this.blogPost === null || this.blogPost.picture.length === 0) ? null : this.$url('upload', this.blogPost.picture)
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
      pulseError(this.$t('error_unexpected'))
    }
  },
}
</script>

<style scoped lang="scss">
.blogpost {
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

<style lang="scss">
.blogpost {
  p {
    margin-bottom: 0 !important;
  }
  ol, ul {
    padding-left: 1.5em;
  }
  .ql-align-center {
    text-align: center;
  }
  .ql-align-right {
    text-align: right;
  }
  .ql-align-justify {
    text-align: justify;
  }
}
</style>

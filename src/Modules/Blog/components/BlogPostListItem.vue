<!-- Item in the list of posts on the blog page -->
<template>
  <div class="news-post">
    <h2>
      <router-link :to="$url('blogPost', blogPost.id)">
        {{ blogPost.title }}
      </router-link>
    </h2>
    <p class="small">
      <span>{{ $t('blog.author') }} {{ blogPost.authorName }}</span>,
      <span>{{ $dateFormatter.format(blogPost.publishedAt) }}</span>
    </p>
    <img v-if="pictureUrl" :src="pictureUrl">
    <div v-text="blogPost.teaser" />
    <p>
      <router-link class="button" :to="$url('blogPost', blogPost.id)">
        {{ $t('blog.read') }}
      </router-link>
    </p>
    <div class="clear" />
  </div>
</template>

<script>
import { BLOG_POST_OPTIONS } from '@/consts'

export default {
  props: {
    blogPost: { type: Object, required: true },
  },
  computed: {
    pictureUrl () {
      return (this.blogPost === null || this.blogPost.picture.length === 0)
        ? null
        : this.$url('upload', this.blogPost.picture, BLOG_POST_OPTIONS.IMAGE.WIDTH, BLOG_POST_OPTIONS.IMAGE.HEIGHT)
    },
  },
}
</script>

<style lang="scss" scoped>
.clear {
  clear: both;
}

.news-post {
  padding: 20px;
  border-bottom: 1px solid var(--fs-border-default);

  h2 a {
    font-weight: unset;
  }

  img {
    border-radius: 6px;
    float: left;
    margin-left: 0;
    margin-right: 15px;
  }

  a.button {
    float: right;
  }
}

@media (max-width: 900px) {
  .news-post img {
    width: 100% !important;
    margin-bottom: 15px;
    margin-right: 0;
    float: none;
  }
}
</style>

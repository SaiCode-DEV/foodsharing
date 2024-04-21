<!-- Item in the list of posts on the blog page -->
<template>
  <div class="news-post">
    <h2>
      <a :href="$url('blogPost', blogPost.id)">{{ blogPost.title }}</a>
    </h2>
    <p class="small">
      <span>{{ $i18n('blog.author') }} {{ blogPost.authorName }}</span>,
      <span>{{ $dateFormatter.format(blogPost.publishedAt) }}</span>
    </p>
    <img v-if="pictureUrl" :src="pictureUrl">
    <!-- eslint-disable vue/no-v-html -->
    <!-- Sanitized in Modules/Blog/BlogGateway.php getPost() -->
    <div v-html="blogPost.content" />
    <!-- eslint-enable -->
    <p>
      <a class="button" :href="$url('blogPost', blogPost.id)">{{ $i18n('blog.read') }}</a>
    </p>
    <div class="clear" />
  </div>
</template>

<script>
export default {
  props: {
    blogPost: { type: Object, required: true },
  },
  computed: {
    pictureUrl () {
      if (this.blogPost === null || this.blogPost.picture.length === 0) {
        return null
      }

      if (this.blogPost.picture.startsWith('/api/uploads/')) {
        return `${this.blogPost.picture}?w=500&h=161` // path for pictures uploaded with the new API
      } else {
        return '/images/' + this.blogPost.picture.replace('/', '/crop_1_528_') // backward compatible path for old pictures
      }
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

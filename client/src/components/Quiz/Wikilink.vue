<template>
  <p>
    <a :href="externalLink" target="_blank">{{ $i18n('quiz.wikilink') }}</a>
  </p>
</template>
<script>
export default {
  props: {
    link: { type: String, required: true },
  },
  computed: {
    externalLink () {
      let url = this.link
      if (!url.startsWith('http')) {
        url = 'https://' + url
      }
      return url
    },
  },
}
</script>

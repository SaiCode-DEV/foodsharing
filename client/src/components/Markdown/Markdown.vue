<template>
  <div
    class="markdown"
    v-html="htmlContent"
  />
</template>
<script>
import markdown from './markdownRenderer'
import { getUserNames } from '@/api/user'
export default {
  props: {
    source: { type: String, required: true },
  },
  data () {
    return {
      htmlContent: '',
    }
  },
  async mounted () {
    this.htmlContent = markdown.render(this.source)
    if (markdown.linkify.data.missingUserNames.size) {
      await this.fetchMissingUserNames()
      this.htmlContent = markdown.render(this.source)
    }
  },
  methods: {
    async fetchMissingUserNames () {
      const data = markdown.linkify.data
      await this.$nextTick()
      if (!data.missingUserNames.size) {
        return await Promise.all(data.fetchResolves)
      }

      const missing = [...data.missingUserNames]
      data.missingUserNames.clear()

      const fetchResolve = getUserNames(missing)
      data.fetchResolves.add(fetchResolve)
      const userNames = await fetchResolve
      data.fetchResolves.delete(fetchResolve)

      Object.assign(data.userNames, userNames)
    },
  },
}
</script>

<style lang="scss">
.markdown {
  p:last-child {
    margin-bottom: 0;
  }
  a {
    word-break: break-word;
  }
  code {
    word-break: break-all;
  }
  img {
    width: 100%;
    max-width: fit-content;
    border: 1px solid var(--fs-border-default);
    border-radius: var(--border-radius);
  }
}
</style>

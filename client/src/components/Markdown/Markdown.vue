<template>
  <!-- the markdown renderer is save -->
  <!-- eslint-disable vue/no-v-html -->
  <div
    class="markdown"
    v-html="htmlContent"
  />
  <!-- eslint-enable -->
</template>
<script>
import markdown from './markdownRenderer'
import { getUserNames } from '@/api/user'
export default {
  props: {
    source: { type: String, required: true },
  },
  data: () => ({ htmlContent: '' }),
  watch: {
    source () {
      this.render()
    },
  },
  async mounted () {
    this.render()
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

      Object.assign(data.userNames, Object.fromEntries(userNames.map(user => [user.id, user.name])))
      const stillMissing = missing.filter(id => !(id in data.userNames))
      Object.assign(data.userNames, Object.fromEntries(stillMissing.map(id => [id, null])))
      sessionStorage.setItem(data.storageKey, JSON.stringify(data.userNames))
    },
    async render () {
      this.htmlContent = markdown.render(this.source)
      if (markdown.linkify.data.missingUserNames.size) {
        await this.fetchMissingUserNames()
        this.htmlContent = markdown.render(this.source)
      }
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
    word-break: break-all;
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

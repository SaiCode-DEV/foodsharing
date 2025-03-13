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
  h1, h2, h3, h4, h5, h6, p, li, a {
    word-break: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
    hyphenate-limit-chars: 12 5 5;
    hyphenate-limit-lines: 2;
    hyphenate-limit-last: always;
    hyphenate-limit-zone: 10%;
  }
  a {
    font-weight: 500 !important;
    display: inline-block;
    max-width: 100%;
    overflow-wrap: anywhere;
    word-break: break-word;
    overflow: hidden;
  }
  code {
    word-break: normal;
    hyphens: none;
    white-space: pre-wrap;
    font-size: inherit;
    display: inline-block;
    max-width: 100%;
    overflow-wrap: anywhere;
    word-break: break-word;
  }
  pre {
    background-color: rgba(0,0,0,.03);
    border: 1px solid var(--fs-border-default);
    border-radius: 6px;
    padding: 1em;
    overflow-x: auto;
    max-width: 100%;
    box-sizing: border-box;
    white-space: pre;
  }
  pre code {
    display: block;
    overflow-wrap: normal;
    word-break: normal;
    white-space: pre;
  }
  blockquote {
    overflow-wrap: break-word;
    word-break: break-word;
  }
  p:last-child {
    margin-bottom: 0;
  }
  ul {
    padding-left: 1em;
  }
  b, strong {
    font-weight: 600;
  }
  img {
    width: 100%;
    max-width: fit-content;
    border: 1px solid var(--fs-border-default);
    border-radius: var(--border-radius);
  }
}
</style>

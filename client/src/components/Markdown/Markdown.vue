<template>
  <!-- htmlContent is DOMPurify-sanitized in render() as defense-in-depth.
       The markdown renderer uses markdown-it with `html: true`; raw HTML is
       currently neutralized because the 'zero' preset does not enable the
       html_block/html_inline rules (it escapes raw HTML). Sanitizing keeps
       v-html safe even if that renderer config ever changes. -->
  <!-- eslint-disable vue/no-v-html -->
  <div
    class="markdown"
    :class="{ inline }"
    @click="onLinkClick"
    v-html="htmlContent"
  />
  <!-- eslint-enable -->
</template>
<script>
import markdown from './markdownRenderer'
import { sanitizeHtml } from '@/helper/sanitize-html'
import { navigate } from '@/helper/router'
import { getUserNames } from '@/api/user'
export default {
  props: {
    source: { type: String, required: true },
    inline: { type: Boolean, default: false },
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
    /**
     * The rendered markdown is injected via v-html, so its links are plain
     * anchors and cannot be <router-link>s. Delegate their clicks to the router
     * instead, so internal links navigate client-side like FsLink does.
     */
    onLinkClick (event) {
      // Let the browser handle modified clicks (new tab/window, save as, ...)
      if (event.defaultPrevented || event.button !== 0) return
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

      const anchor = event.target.closest?.('a[href]')
      if (!anchor || !this.$el.contains(anchor) || !this.$router) return

      // Leave everything the router cannot handle to the browser: new-tab
      // links, downloads and in-page anchors.
      const target = anchor.getAttribute('target')
      if ((target && target !== '_self') || anchor.hasAttribute('download')) return
      if (anchor.getAttribute('href').startsWith('#')) return

      let url
      try {
        // anchor.href is already resolved against the document.
        url = new URL(anchor.href)
      } catch (e) {
        return
      }
      // Other origins, mailto:, tel:, javascript: -> not a router target.
      if (url.origin !== window.location.origin) return

      event.preventDefault()
      navigate(url.pathname + url.search + url.hash)
    },
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
    renderSource () {
      return this.inline ? markdown.renderInline(this.source) : markdown.render(this.source)
    },
    async render () {
      this.htmlContent = sanitizeHtml(this.renderSource())
      if (markdown.linkify.data.missingUserNames.size) {
        await this.fetchMissingUserNames()
        this.htmlContent = sanitizeHtml(this.renderSource())
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
    display: inline;
    max-width: 100%;
    overflow-wrap: anywhere;
    word-break: break-word;
    overflow: hidden;
  }
  code {
    word-break: normal;
    hyphens: none;
    white-space: normal;
    font-size: inherit;
    display: inline;
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
    max-width: 100%;
    box-sizing: border-box;
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
  table {
    display: block;
    width: max-content;
    max-width: 100%;
    overflow-x: auto;
    border-collapse: collapse;
    margin-bottom: 1rem;
  }
  th, td {
    border: 1px solid var(--fs-border-default);
    padding: 0.25rem 0.5rem;
  }
  th {
    background-color: rgba(0,0,0,.03);
  }

  &.inline {
    display: inline;
  }
}
</style>

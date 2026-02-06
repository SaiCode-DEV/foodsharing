<template>
  <a
    :href="$url('forumThread', thread.region_id, thread.id, thread.postId)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <!-- eslint-disable vue/no-v-html  Made save using DOMPurify -->
      <div
        v-if="isBody && thread.body"
        class="thread-body-excerpt"
        v-html="highlightedExcerpt"
      />
      <!-- eslint-enable vue/no-v-html -->
    </div>
  </a>
</template>
<script setup>
import { defineProps, computed } from 'vue'
import DOMPurify from 'dompurify'

const props = defineProps({
  thread: {
    type: Object,
    required: true,
  },
  hideRegion: {
    type: Boolean,
    default: false,
  },
  query: {
    type: String,
    default: '',
  },
  isBody: {
    type: Boolean,
    default: false,
  },
  isGrouped: {
    type: Boolean,
    default: false,
  },
})

const highlightedExcerpt = computed(() => {
  if (!props.isBody || !props.thread.body || !props.query) {
    return ''
  }

  const body = props.thread.body
  const query = props.query.trim()

  if (!query) return ''

  // Escape special regex characters in query
  const escapeRegex = (str) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

  // Split query into words and escape them
  const queryWords = query.split(/\s+/).filter(word => word.length > 0)

  // Find the first occurrence of any query word
  let bestMatch = -1

  for (const word of queryWords) {
    const escapedWord = escapeRegex(word)
    const regex = new RegExp(escapedWord, 'i')
    const match = body.search(regex)
    if (match !== -1 && (bestMatch === -1 || match < bestMatch)) {
      bestMatch = match
    }
  }

  if (bestMatch === -1) {
    // No match found, return beginning of body
    return body.substring(0, 150) + '...'
  }

  // Extract context around the match (about 150 characters)
  const contextLength = 75
  const start = Math.max(0, bestMatch - contextLength)
  const end = Math.min(body.length, bestMatch + contextLength)

  let excerpt = body.substring(start, end)

  // Add ellipsis if needed
  if (start > 0) excerpt = '...' + excerpt
  if (end < body.length) excerpt = excerpt + '...'

  // Highlight all query words in the excerpt
  queryWords.forEach(word => {
    const escapedWord = escapeRegex(word)
    const regex = new RegExp(`(${escapedWord})`, 'gi')
    excerpt = excerpt.replace(regex, '<strong>$1</strong>')
  })

  // Sanitize HTML to prevent XSS attacks, only allowing <strong> tags
  return DOMPurify.sanitize(excerpt, {
    ALLOWED_TAGS: ['strong'],
    ALLOWED_ATTR: [],
  })
})
</script>

<style lang="scss" scoped>
.search-result {
  border-bottom: 1px solid var(--fs-color-gray-300);

  &:last-child {
    border-bottom: none;
  }

  &:hover {
    background-color: var(--fs-color-gray-200);
  }
}

.thread-group .search-result {
  border-bottom: 1px solid var(--fs-color-gray-200);

  &:first-child {
    padding-top: 0.5rem !important;
  }

  &:last-child {
    border-bottom: none;
    padding-bottom: 0.5rem !important;
  }
}

.thread-body-excerpt {
  font-size: 0.85rem;
  color: var(--fs-color-gray-600);
  line-height: 1.4;
  white-space: normal !important;
  word-wrap: break-word;
  overflow-wrap: break-word;
  max-width: 100%;

  ::v-deep strong {
    font-weight: 700;
    color: var(--fs-color-gray-800);
    background-color: rgba(255, 235, 59, 0.3);
    padding: 0 2px;
    border-radius: 2px;
  }
}
</style>

<template>
  <b-modal
    :id="id"
    :title="$t('bell.translation_failed.title')"
    :ok-only="true"
    :ok-title="$t('button.ok')"
    centered
  >
    <Markdown :source="$t('bell.translation_failed.md', { bellTitle, bellKey })" />
    <div class="dropdown-divider my-4" />
    <p>
      <a
        :href="betaUrl"
        target="_blank"
        rel="noopener"
      >
        {{ betaUrl }}
      </a>
    </p>
    <Markdown :source="$t('bell.translation_failed.md_support')" />
  </b-modal>
</template>

<script setup>
import { defineProps, computed } from 'vue'
import Markdown from '@/components/Markdown/Markdown.vue'

const props = defineProps({
  id: { type: String, required: true },
  href: { type: String, required: true },
  bellTitle: { type: String, default: '' },
  bellKey: { type: String, default: '' },
})

const betaUrl = computed(() => {
  // Create a beta URL based on the original URL
  // Prepend 'beta.' to the hostname part of the URL
  let url = props.href

  // If it's a relative URL, make it absolute
  if (!url.startsWith('http')) {
    url = window.location.origin + (url.startsWith('/') ? '' : '/') + url
  }

  try {
    const urlObj = new URL(url)
    // Insert 'beta.' before the hostname
    urlObj.hostname = 'beta.' + urlObj.hostname
    return urlObj.toString()
  } catch (e) {
    // Fallback if URL parsing fails
    const origin = window.location.origin
    const domain = origin.replace(/^https?:\/\//, '')
    return origin.replace(domain, 'beta.' + domain) + (url.startsWith('/') ? '' : '/') + url
  }
})
</script>

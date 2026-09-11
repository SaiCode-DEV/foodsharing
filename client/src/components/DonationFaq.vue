<template>
  <b-card class="mb-5 faq-card">
    <div class="faq-dotted-line faq-dotted-line-top" />
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-1">
          {{ $t('donation_page.faq.title') }}
        </h3>
        <div class="mb-2">
          {{ $t('donation_page.faq.teaser') }}
        </div>
      </div>
      <div
        class="d-flex align-items-center"
        role="group"
        aria-label="FAQ toggle buttons"
      />
    </div>

    <div
      v-for="faqKey in faqKeys"
      :key="faqKey"
      class="mb-2"
    >
      <div
        v-b-toggle="'faq-accordion-' + faqKey"
        class="d-flex justify-content-between align-items-center p-2 faq-question"
        role="button"
      >
        <div class="flex-grow-1">
          <h4
            class="mb-0 faq-heading"
            tabindex="0"
          >
            {{ $t(`donation_page.faq.question_${faqKey}`) }}
          </h4>
        </div>
        <i class="fas fa-chevron-down faq-chevron ml-2" />
      </div>
      <b-collapse
        :id="`faq-accordion-${faqKey}`"
        accordion="faq"
        class="px-2 pb-2"
      >
        <Markdown :source="$t(`donation_page.faq.answer_${faqKey}`)" />
      </b-collapse>
    </div>

    <div class="faq-dotted-line faq-dotted-line-bottom" />
  </b-card>
</template>

<script setup>
import { computed } from 'vue'
import { i18nInstance } from '@/helper/i18n'
import Markdown from '@/components/Markdown/Markdown.vue'

const faqKeys = computed(() => {
  const faq = i18nInstance.messages?.[i18nInstance.locale]?.donation_page?.faq || {}
  return Object
    .keys(faq)
    .map(k => k.match(/^question_(\d+)$/))
    .filter(Boolean)
    .map(m => Number(m[1]))
    .sort((a, b) => a - b)
})
</script>

<style scoped>
.faq-question {
  cursor: pointer;
}

.faq-chevron {
  transition: transform 0.2s ease;
  font-size: 1.2em;
  color: #6b3a32;
}

.faq-question.not-collapsed .faq-chevron {
  transform: rotate(180deg);
}
</style>

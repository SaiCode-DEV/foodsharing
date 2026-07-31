<template>
  <div>
    <Container
      id="legal-wrapper"
      :title="$t('legal.privacy_policy')"
      :collapsible="false"
    >
      <!-- eslint-disable vue/no-v-html -->
      <div
        class="list-group-item scrolling"
        :class="{ 'highlight-changes': showChanges }"
        v-html="showChanges ? privacyPolicyChanges?.body : privacyPolicy?.body"
      />
      <!-- eslint-enable -->
      <div class="list-group-item elevated">
        <span v-text="$t('legal.latest_change', { date: (new Date(privacyPolicy?.lastModified)).toLocaleDateString()})" />
        <b-form-checkbox
          v-model="showChanges"
          class="float-right"
          switch
        >
          {{ $t('legal.show_changes') }}
        </b-form-checkbox>
      </div>
    </Container>
    <Container
      v-if="userStore.isStoreManager"
      :title="$t('legal.privacy_notice')"
      :collapsible="false"
      :wrap-content="true"
    >
      <!-- Sanitized in Modules/Content/ContentGateway.php get() -->
      <!-- eslint-disable vue/no-v-html -->
      <div v-html="privacyNoticeContent?.body" />
      <!-- eslint-enable vue/no-v-html -->
    </Container>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Container from '@/components/Container/Container.vue'
import { CONTENT_IDS, getContent } from '@/api/content'
import { useUserStore } from '@/stores/user'

const privacyPolicyChanges = ref(null)
const showChanges = ref(false)
const userStore = useUserStore()

onMounted(getLegalContent)

const privacyPolicy = ref(null)
const privacyNoticeContent = ref(null)

async function getLegalContent () {
  [privacyPolicy.value, privacyNoticeContent.value, privacyPolicyChanges.value] = await Promise.all([
    getContent(CONTENT_IDS.PRIVACY_POLICY_CONTENT).catch(() => null),
    getContent(CONTENT_IDS.PRIVACY_NOTICE_CONTENT).catch(() => null),
    getContent(CONTENT_IDS.PRIVACY_POLICY_CHANGES).catch(() => null),
  ])
}
</script>
<style scoped>
.scrolling {
  max-height: 70vh;
  overflow-y: auto;
}
.elevated {
  background-color: var(--fs-color-elevated);
}
</style>
<style lang="scss">
.highlight-changes {
  strong {
    background-color: var(--fs-color-success-alpha-20);
    color: var(--fs-color-success-700);
    font-weight: inherit;
  }
  s {
    background-color: var(--fs-color-danger-alpha-10);
    color: var(--fs-color-danger-500);
  }
  blockquote {
    font-style: italic;
  }
}
</style>

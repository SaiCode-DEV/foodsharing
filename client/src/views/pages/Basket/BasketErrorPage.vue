<template>
  <Container
    :collapsible="false"
    hide-header
    class="p-3"
  >
    <h1>
      {{ $t('terminology.basket') }} <span class="text-secondary">#{{ id }}</span>
    </h1>
    <Markdown :source="$t('basket.error.taken', { id })" class="mb-2" />
    <Markdown
      v-if="!userStore.isLoggedIn"
      :source="$t('basket.error.register')"
      class="mb-2"
    />
    <Markdown :source="$t('basket.error.map')" />
    <div>
      <b-button
        variant="primary"
        class="mt-3"
        :to="url('baskets')"
      >
        {{ $t('basket.all_map') }}
      </b-button>
      <b-button
        v-if="!userStore.isLoggedIn"
        variant="primary"
        class="mt-3"
        :to="url('joininfo')"
      >
        {{ $t('register.topbar') }}
      </b-button>
    </div>
  </Container>
</template>
<script setup>
import { defineProps } from 'vue'
import { useUserStore } from '@/stores/user'
import { url } from '@/helper/urls'
import Markdown from '@/components/Markdown/Markdown.vue'
import Container from '@/components/Container/Container.vue'

const userStore = useUserStore()

defineProps({
  id: { type: String, default: '' },
})
</script>

<template>
  <map-popup
    id="basketBubbleModal"
    ref="basketBubbleModal"
    :is-loading="loading"
  >
    <div v-if="bubbleData.pictures?.length" class="mb-3 content-block">
      <ResponsiveImage
        v-if="bubbleData.pictures.length === 1"
        :image="bubbleData.pictures[0]"
        :height-in-px="300"
        :min-width-in-px="465"
        :max-width-in-px="465"
      />
      <Gallery
        v-else
        :height-in-px="300"
        :images="bubbleData.pictures"
      />
    </div>

    <div
      v-if="userStore.isLoggedIn && bubbleData.createdAt"
      class="mb-3 content-block"
    >
      <div
        class="mb-1 section-label"
      >
        {{ $t('basket.date') }}
      </div>
      <div>{{ displayDate }}</div>
    </div>

    <div class="mb-3 content-block">
      <div
        class="mb-1 section-label"
      >
        {{ $t('basket.description') }}
      </div>
      <div>{{ bubbleData.description }}</div>
    </div>

    <template #popup-header>
      <h3 v-if="userStore.isLoggedIn && bubbleData?.creator?.name">
        {{ $t('basket.by', { name: bubbleData.creator.name }) }}
      </h3>
      <h3 v-else>
        {{ $t('terminology.basket') }}
      </h3>
    </template>
    <template #popup-footer>
      <a
        class="btn btn-primary"
        type="button"
        :href="$url('basket', basketId)"
        v-text="$t('basket.go')"
      />
    </template>
  </map-popup>
</template>

<script setup>
import { ref, computed, defineExpose } from 'vue'
import { getBasketBubbleContent } from '@/api/map'
import { useUserStore } from '@/stores/user'
import MapPopup from './MapPopup.vue'
import Gallery from '@/components/Images/Gallery.vue'
import ResponsiveImage from '@/components/Images/ResponsiveImage.vue'
import DateFormatter from '@/helper/date-formatter.js'

const userStore = useUserStore()

const bubbleData = ref('')
const basketId = ref(null)
const loading = ref(true)

const basketBubbleModal = ref(null)

const displayDate = computed(() => {
  return bubbleData.value.createdAt
    ? DateFormatter.dateTime(bubbleData.value.createdAt)
    : null
})

async function show (id) {
  basketId.value = id
  bubbleData.value = await getBasketBubbleContent(basketId.value)
  basketBubbleModal.value.show()
  loading.value = false
}

defineExpose({
  show,
})
</script>

<style>
.basketpicture {
  width: 100%;
  overflow: hidden;
}
.section-label {
  color: var(--fs-color-primary-500);
  font-weight: 500;
}
.content-block:last-child {
  margin-bottom: 0 !important;
}
</style>

<template>
  <map-popup id="basketBubbleModal" :is-loading="loading">
    <div v-if="bubbleData.photo" class="mb-2 mt-2">
      <img class="basketpicture" :src="photoPath">
    </div>

    <div
      v-if="isLoggedIn && bubbleData.createdAt"
      class="mb-3"
    >
      <div
        class="mb-1 section-label"
      >
        {{ $i18n('basket.date') }}
      </div>
      <div>{{ displayDate }}</div>
    </div>

    <div
      class="mb-1 section-label"
    >
      {{ $i18n('basket.description') }}
    </div>
    <div class="mb-3">
      {{ bubbleData.description }}
    </div>

    <template #popup-header>
      <h3 v-if="isLoggedIn && bubbleData?.creator?.name">
        {{ $i18n('basket.by', { name: bubbleData.creator.name }) }}
      </h3>
      <h3 v-else>
        {{ $i18n('terminology.basket') }}
      </h3>
    </template>
    <template #popup-footer>
      <a
        class="btn btn-primary mx-5"
        type="button"
        :href="$url('basket', basketId)"
        v-text="$i18n('basket.go')"
      />
    </template>
  </map-popup>
</template>

<script>
import { getBasketBubbleContent } from '@/api/map'
import DataUser from '@/stores/user'
import MapBubbleMixin from './MapBubbleMixin'

export default {
  mixins: [MapBubbleMixin],
  data: () => ({
    bubbleData: '',
    basketId: null,
  }),
  computed: {
    isLoggedIn () {
      return DataUser.getters.isLoggedIn()
    },
    photoPath () {
      return this.bubbleData.photo.startsWith('/api')
        ? this.bubbleData.photo + '?w=300&h=300'
        : `/images/basket/medium-${this.bubbleData.photo}`
    },
    displayDate () {
      return this.bubbleData.createdAt
        ? this.$dateFormatter.format(this.bubbleData.createdAt, {
          day: 'numeric',
          weekday: 'long',
          month: 'short',
          hour: 'numeric',
          minute: 'numeric',
        })
        : null
    },
  },
  methods: {
    async show (basketId) {
      this.basketId = basketId
      await this.timedFetchAction(
        getBasketBubbleContent(this.basketId),
        'basketBubbleModal',
        (data) => { this.bubbleData = data },
      )
    },
  },
}
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
</style>

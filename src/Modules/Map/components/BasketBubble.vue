<template>
  <map-popup id="basketBubbleModal" :is-loading="loading">
    <div v-if="bubbleData.pictures?.length" class="mb-2 mt-2">
      <b-carousel indicators controls>
        <b-carousel-slide
          v-for="(photoPath, i) in photoPaths"
          :key="i"
          :img-src="photoPath"
        />
      </b-carousel>
    </div>

    <div
      v-if="userStore.isLoggedIn && bubbleData.createdAt"
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
      <h3 v-if="userStore.isLoggedIn && bubbleData?.creator?.name">
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
import { useUserStore } from '@/stores/user'
import MapBubbleMixin from './MapBubbleMixin'

const userStore = useUserStore()

export default {
  mixins: [MapBubbleMixin],
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      bubbleData: '',
      basketId: null,
    }
  },
  computed: {
    photoPaths () {
      const photos = this.bubbleData?.pictures ?? []
      return photos.map(photo => photo.startsWith('/api')
        ? photo + '?w=465&h=300'
        : `/images/basket/medium-${photo}`,
        // TOOD This destinction can be removed three weeks after Update "N", since all active baskets will be replaced by that time.
      )
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

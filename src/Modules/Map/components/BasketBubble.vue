<template>
  <map-popup id="basketBubbleModal" :is-loading="loading">
    <div v-if="bubbleData.pictures?.length" class="mb-3">
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
      class="mb-3"
    >
      <div
        class="mb-1 section-label"
      >
        {{ $t('basket.date') }}
      </div>
      <div>{{ displayDate }}</div>
    </div>

    <div
      class="mb-1 section-label"
    >
      {{ $t('basket.description') }}
    </div>
    <div class="mb-3">
      {{ bubbleData.description }}
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
        class="btn btn-primary mx-5"
        type="button"
        :href="$url('basket', basketId)"
        v-text="$t('basket.go')"
      />
    </template>
  </map-popup>
</template>

<script>
import { getBasketBubbleContent } from '@/api/map'
import { useUserStore } from '@/stores/user'
import MapBubbleMixin from './MapBubbleMixin'
import Gallery from '@/components/Images/Gallery.vue'
import ResponsiveImage from '@/components/Images/ResponsiveImage.vue'

const userStore = useUserStore()

export default {
  components: { Gallery, ResponsiveImage },
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

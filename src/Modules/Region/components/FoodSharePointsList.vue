<template>
  <div class="field">
    <div class="head ui-widget-header ui-corner-top">
      {{ $i18n('fsp.twig.n_in_region', { count: foodSharePoints.length, name: regionName }) }}
    </div>

    <!-- body -->
    <div class="ui-widget ui-widget-content corner-all margin-bottom corner-bottom">
      <i v-if="isLoading" class="fas fa-spinner fa-spin" />
      <ul v-else class="linklist food-share-point-list">
        <li
          v-for="fsp in foodSharePoints"
          :key="fsp.id"
        >
          <a
            :href="$url('foodsharepoint', fsp.id)"
            class="row"
          >
            <img
              :src="pictureUrl(fsp)"
              :alt="$i18n('picture')"
              class="image"
            >
            <span class="d-inline fsp-name">{{ fsp.name }}</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import { listFoodSharePoints } from '@/api/foodsharepoints'
import { pulseError } from '@/script'

export default {
  props: {
    regionId: { type: Number, required: true },
    regionName: { type: String, required: true },
  },
  data () {
    return {
      isLoading: false,
      foodSharePoints: [],
    }
  },
  async mounted () {
    this.isLoading = true

    try {
      this.foodSharePoints = await listFoodSharePoints(this.regionId)
    } catch (e) {
      pulseError(this.$i18n('error_unexpected'))
    }

    this.isLoading = false
  },
  methods: {
    pictureUrl (fsp) {
      if (fsp.picture) {
        if (fsp.picture.startsWith('/api/uploads/')) {
          return fsp.picture + '?w=55&h=55' // path for pictures uploaded with the new API
        } else {
          return '/images' + fsp.picture.replace('/', '/crop_1_60_') + fsp.picture // backward compatible path for old pictures
        }
      } else {
        return 'img/foodSharePointThumb.png'
      }
    },
  },
}
</script>

<style lang="scss" scoped>
ul.food-share-point-list li a {
  img {
    width: 55px;
    height: 55px;
    border-radius: 5px;
  }

  .fsp-name {
    margin-left: 10px;
    text-decoration: none;
    color: var(--fs-color-primary-500);
  }
}
</style>

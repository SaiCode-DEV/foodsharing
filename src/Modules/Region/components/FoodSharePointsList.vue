<template>
  <Container :title="$t('fsp.twig.n_in_region', { count: foodSharePoints.length, name: regionName })">
    <i v-if="isLoading" class="fas fa-spinner fa-spin" />
    <div v-else class="p-2">
      <b-button
        variant="primary"
        class="float-right"
        :to="$url('foodsharepointAdd', regionId)"
      >
        {{ foodSharePointPermission ? $t('fsp.add') : $t('fsp.suggest') }}
      </b-button>
      <div class="pt-5">
        <ul class="linklist food-share-point-list">
          <li
            v-for="foodSharePoint in foodSharePoints"
            :key="foodSharePoint.id"
          >
            <router-link
              :to="$url('foodsharepoint', foodSharePoint.id)"
            >
              <img
                :src="pictureUrl(foodSharePoint)"
                :alt="$t('picture')"
              >
              <span class="d-inline fsp-name">{{ foodSharePoint.name }}</span>
            </router-link>
          </li>
        </ul>
      </div>
    </div>
  </Container>
</template>

<script>
import { listFoodSharePoints } from '@/api/foodsharepoints'
import Container from '@/components/Container/Container.vue'
import { pulseError } from '@/script'

export default {
  components: { Container },
  props: {
    regionId: { type: Number, required: true },
    regionName: { type: String, required: true },
    foodSharePointPermission: { type: Boolean, required: true },
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
      pulseError(this.$t('error_unexpected'))
    }

    this.isLoading = false
  },
  methods: {
    pictureUrl (fsp) {
      if (!fsp.picture) return 'img/foodSharePointThumb.png'
      return fsp.picture + '?w=55&h=55'
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

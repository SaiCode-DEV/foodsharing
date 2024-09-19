<template>
  <a
    class="list-group-item list-group-item-action field"
    :href="$url('basket', entry.id)"
  >
    <div class="img-thumbnail mr-2">
      <img
        :alt="$i18n('basket.by', { name: entry.creator.name })"
        :src="getImageUrl(entry.picture)"
        class="rounded"
        width="35"
        height="35"
        loading="lazy"
      >
    </div>
    <div class="field-container field-container--stack">
      <!-- eslint-disable vue/no-v-html -->
      <!-- Sanitized in Modules/Basket/BasketGateway.php getBasket() -->
      <div class="field-container">
        <h6
          v-b-tooltip="entry.description.length > 30 ? entry.description : ''"
          class="field-headline"
          v-html="entry.description"
        />
      </div>
      <!-- eslint-enable -->
      <div class="field-container">
        <small
          v-b-tooltip="$i18n('basket.by', { name: entry.creator.name })"
          class="field-subline field-subline--muted"
        >
          {{ $i18n('basket.expires') }} <strong>{{ $dateFormatter.dateTime(new Date(entry.until * 1000)) }} </strong>
        </small>
        <span
          v-if="entry.lat && entry.lon"
          class="ml-2 badge list-group-item-dark badge-pill"
        >
          <i v-if="distanceNumber > 0" class="fas fa-directions" />
          {{ distanceString(distanceNumber) }}
        </span>
      </div>
    </div>
  </a>
</template>

<script>
// Stores
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

export default {
  props: {
    entry: { type: Object, default: () => {} },
  },
  setup () {
    return {
      userStore,
    }
  },
  computed: {
    distanceNumber () {
      return this.getDistanceNumber(this.entry.lat, this.entry.lon)
    },
  },
  methods: {
    // distanceNumber (num) {
    //   return Math.round(num * 1000) / 1000
    // },
    distanceString (num) {
      // num = this.distanceNumber(num)
      if (num === 0) {
        return '💑'
      } else if (num < 1) {
        return `${(num * 1000).toLocaleString()} m`
      } else {
        return `${(num).toFixed(1).toLocaleString()} km`
      }
    },
    getImageUrl (picture) {
      // TODO: at most 3 weeks after the next release no active basket references an image using the /images/basket/... format.
      // All of these images, as well as this additional logic can be deleted than.
      // There are more places in the code base referencing this. Simply search for /images/basket to find all of them.
      if (picture) {
        if (picture.startsWith('/api')) {
          return `${picture}?w=35&h=35`
        }
        return `/images/basket/thumb-${picture}`
      }
      return '/img/basket.png'
    },
    getDistanceNumber (lat, lon) {
      const deg2rad = (degrees) => degrees * (Math.PI / 180)
      const uC = useUserStore().getLocations
      const R = 6371 // Radius of the earth in km
      const dLat = deg2rad(uC.lat - lat) // deg2rad below
      const dLon = deg2rad(uC.lon - lon)
      const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(deg2rad(lat)) * Math.cos(deg2rad(uC.lat)) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2)
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
      const d = R * c // Distance in km
      return Math.round(d * 1000) / 1000
    },
  },
}
</script>

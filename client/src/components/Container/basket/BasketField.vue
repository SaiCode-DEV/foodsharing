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
          {{ $i18n('basket.until') }} {{ $dateFormatter.dateTime(new Date(entry.until * 1000)) }}
        </small>
        <span class="ml-2 badge list-group-item-dark badge-pill">
          <i v-if="entry.distanceInKm > 0" class="fas fa-directions" />
          {{ distanceString(entry.distanceInKm) }}
        </span>
      </div>
    </div>
  </a>
</template>

<script>
export default {
  props: {
    entry: { type: Object, default: () => {} },
  },
  methods: {
    distanceString (distanceInKm) {
      if (distanceInKm < 1) {
        return `${(Math.round(distanceInKm * 100) * 10).toLocaleString()} m`
      } else {
        return `${(distanceInKm).toFixed(1).toLocaleString()} km`
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
  },
}
</script>

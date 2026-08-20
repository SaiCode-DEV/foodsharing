<template>
  <FsLink
    :to="$url('basket', entry.id)"
    class="list-group-item list-group-item-action field"
  >
    <div class="img-thumbnail mr-2">
      <img
        :alt="$t('basket.by', { name: entry.creator.name })"
        :src="getImageUrl(entry.picture)"
        class="rounded"
        width="35"
        height="35"
        loading="lazy"
      >
    </div>
    <div class="field-container field-container--stack">
      <!-- eslint-disable vue/no-v-html -->
      <!-- entry.description is user-provided and NOT sanitized server-side
           (no sanitization in BasketGateway add/getBasket). Sanitized client-
           side via the shared hardened sanitizer. The v-b-tooltip binding is
           safe because bootstrap-vue escapes the title unless `.html`. -->
      <div class="field-container">
        <h6
          v-b-tooltip="entry.description.length > 30 ? entry.description : ''"
          class="field-headline"
          v-html="sanitizedDescription"
        />
      </div>
      <!-- eslint-enable -->
      <div class="field-container">
        <small
          v-b-tooltip="$t('basket.by', { name: entry.creator.name })"
          class="field-subline field-subline--muted"
        >
          {{ $t('basket.until') }} {{ $dateFormatter.dateTime(new Date(entry.until)) }}
        </small>
        <span class="ml-2 badge list-group-item-dark badge-pill">
          <i v-if="entry.distanceInKm > 0" class="fas fa-directions" />
          {{ distanceString(entry.distanceInKm) }}
        </span>
      </div>
    </div>
  </FsLink>
</template>

<script>
import { sanitizeHtml } from '@/helper/sanitize-html'
import FsLink from '@/components/UI/FsLink.vue'

export default {
  components: { FsLink },
  props: {
    entry: { type: Object, default: () => {} },
  },
  computed: {
    sanitizedDescription () {
      return sanitizeHtml(this.entry.description)
    },
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
      return picture ? this.$url('upload', picture, 35, 35) : '/img/basket.png'
    },
  },
}
</script>

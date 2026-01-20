<template>
  <b-modal
    id="SlotsModal"
    :title="sumFormatted + ' ' + $t('profile.stats.slots')"
    hide-footer
  >
    <div class="p-2">
      <ul class="list-unstyled mb-0">
        <li>
          <strong>{{ $t('profile.stats.fetch_count') }}:</strong>
          <span class="ml-2">{{ fetchCountFormatted }} x</span>
        </li>
        <li>
          <strong>{{ $t('profile.stats.give_count') }}:</strong>
          <span class="ml-2">{{ giveCountFormatted }} x</span>
        </li>
        <li>
          <strong>{{ $t('profile.stats.engagement_count') }}:</strong>
          <span class="ml-2">{{ engageCountFormatted }} x</span>
        </li>
      </ul>
    </div>
  </b-modal>
</template>

<script>
import { locale } from '@/helper/i18n'

const formatNumber = (number, unit) => {
  if (number === undefined || number === null || isNaN(number)) {
    return '0'
  }

  const options = {
    notation: 'compact',
    maximumFractionDigits: 2,
  }

  return new Intl.NumberFormat(locale, options).format(number)
}

export default {
  name: 'SlotsModal',
  props: {
    fetchCount: { type: Number, default: 0 },
    giveCount: { type: Number, default: 0 },
    engageCount: { type: Number, default: 0 },
  },
  computed: {
    fetchCountFormatted () {
      return this.fetchCount >= 0 ? formatNumber(this.fetchCount) : '0'
    },
    giveCountFormatted () {
      return this.giveCount >= 0 ? formatNumber(this.giveCount) : '0'
    },
    engageCountFormatted () {
      return this.engageCount >= 0 ? formatNumber(this.engageCount) : '0'
    },
    sumFormatted () {
      const sum = (this.fetchCount || 0) + (this.giveCount || 0) + (this.engageCount || 0)
      return formatNumber(sum)
    },
  },
}
</script>

<style scoped>
.p-2 {
  padding: 0.5rem;
}
</style>

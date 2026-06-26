<template>
  <Container
    :title="$t('statistics.lastMonth')"
    tag="publicRegionStatistics"
    info-key="publicRegionStatistics"
  >
    <div
      v-for="(value, key) of displayableFields"
      :key="key"
      class="list-group-item py-2 px-2 d-flex"
    >
      <span class="fa-stack text-secondary mr-2" style="font-size: 1.75em;">
        <i class="fas fa-circle fa-stack-2x" />
        <i :class="`fa-${statIcons[key]}`" class="fas fa-stack-1x fa-inverse" />
      </span>
      <span class="stat-desc">
        <h4 class="my-0" v-text="formatNumber(value, key === 'savedFoodKgLastMonth' ? 'kg' : '')" />
        <span v-text="$t(`statistics.region_public.${key}`)" />
      </span>
    </div>
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'

export default {
  components: { Container },
  props: {
    statistics: { type: Object, required: true },
  },
  data: () => ({
    statIcons: {
      pickupsLastMonth: 'people-carry',
      savedFoodKgLastMonth: 'apple-alt',
      activeHomeRegionFoodsavers: 'users',
      activeCoorporations: 'store-alt',
      activeFoodSharePoints: 'recycle',
      foodBasketsLastMonth: 'shopping-basket',
    },
  }),
  computed: {
    displayableFields () {
      // Filter out the lastUpdated field from the statistics
      const { lastUpdated: _, ...rest } = this.statistics
      return rest
    },
  },
  methods: {
    formatNumber (number, unit = '') {
      if (unit === 'kg' && number >= 1000) {
        return this.formatNumber(number / 1000, 't')
      }
      if (!number) {
        return '0'
      }
      const separator = '\u202F'
      unit = separator + unit
      if (number >= 1_000_000) {
        return this.formatNumber(number / 1_000_000) + separator + 'Mio.' + unit
      } else if (number < 100) {
        return number.toFixed(2 - Math.floor(Math.log10(number))).replace('.', ',').replace(/,0+$/, '') + unit
      } else {
        return number.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, separator) + unit
      }
    },
  },
}
</script>
<style scoped>
.stat-badge {
  font-size: 1.75em;
}
.stat-icon {
  margin-left: -.25em;
}
.stat-value {
  font-size: 0.75em;
  font-weight: 500;
  margin-right: -.25em;
  margin-left: .25em;
  vertical-align: middle;
}
.stat-desc {
  align-self: center;
}
</style>

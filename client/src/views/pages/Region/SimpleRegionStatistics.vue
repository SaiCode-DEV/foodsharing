<template>
  <Container :title="$i18n('statistics.title')" tag="publicRegionStatistics">
    <div
      v-for="(value, key) of statistics"
      :key="key"
      class="list-group-item py-2 px-2 d-flex"
    >
      <b-badge
        variant="success"
        pill
        class="stat-badge mr-2"
      >
        <i :class="`fas fa-${statIcons[key]}`" />
        <span class="stat-value" v-text="value + (key === 'savedFoodKgLastMonth' ? ' kg' : '')" />
      </b-badge>
      <span class="stat-desc" v-text="$i18n(`statistics.region_public.${key}`)" />
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

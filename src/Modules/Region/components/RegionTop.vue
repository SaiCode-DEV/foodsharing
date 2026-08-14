<template>
  <b-alert show>
    <router-link :to="$url('publicRegion', regionId)">
      <h4>{{ name }}</h4>
    </router-link>
    <div class="regionTopClass">
      {{ $t('terminology.n_foodsaver', {count: $n(foodSaverCount, 'text')}) }},
      <span v-if="!isWorkGroup">{{ $t('terminology.n_home_regions', {count: $n(foodSaverHomeDistrictCount, 'text')}) }},</span>
      {{ $t('terminology.n_sleeping', {count: $n(foodSaverHasSleepingHatCount, 'text')}) }}

      <span v-if="!isWorkGroup">, {{ $t('terminology.n_ambassadors', {count: $n(ambassadorCount, 'text')}) }},
        {{ $t('terminology.n_stores', {count: $n(storesCount, 'text')}) }},
        {{ $t('terminology.n_cooperating_stores', {count: $n(storesCooperationCount, 'text')}) }},
        {{ $t('statistics.food_weight_saved', {weight: formatWeight(storesFetchedWeight)}) }},
        {{ $t('statistics.num_fetches', {count: $n(storesPickupsCount, 'text')}) }}</span>
    </div>
  </b-alert>
</template>

<script>
import { formatWeight } from '@/utils'
export default {
  props: {
    regionId: { type: Number, required: true },
    name: { type: String, required: true },
    foodSaverCount: { type: Number, required: true },
    foodSaverHomeDistrictCount: { type: Number, required: true },
    foodSaverHasSleepingHatCount: { type: Number, required: true },
    ambassadorCount: { type: Number, required: true },
    storesCount: { type: Number, required: true },
    storesCooperationCount: { type: Number, required: true },
    storesPickupsCount: { type: Number, required: true },
    storesFetchedWeight: { type: Number, required: true },
    isWorkGroup: { type: Boolean, required: true },
  },
  methods: {
    formatWeight,
  },
}
</script>

<style scoped>
 .regionTopClass {
   color: var(--fs-color-primary-600);
   font-size: 1.2rem
 }
</style>

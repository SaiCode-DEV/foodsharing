<template>
  <b-alert show>
    <a :href="$url('publicRegion', regionId)"><h4>{{ name }}</h4></a>
    <div class="regionTopClass">
      {{ $t('terminology.n_foodsaver', {count: foodSaverCount}) }},
      <span v-if="!isWorkGroup">{{ $t('terminology.n_home_regions', {count: foodSaverHomeDistrictCount}) }},</span>
      {{ $t('terminology.n_sleeping', {count: foodSaverHasSleepingHatCount}) }}

      <span v-if="!isWorkGroup">, {{ $t('terminology.n_ambassadors', {count: ambassadorCount}) }},
        {{ $t('terminology.n_stores', {count: storesCount}) }},
        {{ $t('terminology.n_cooperating_stores', {count: storesCooperationCount}) }},
        {{ $t('statistics.kg_food_saved', {count: storesFetchedWeight}) }},
        {{ $t('statistics.num_fetches', {count: storesPickupsCount}) }}</span>
    </div>
  </b-alert>
</template>

<script>

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
}
</script>

<style scoped>
 .regionTopClass {
   color: var(--fs-color-primary-600);
   font-size: 1.2rem
 }
</style>

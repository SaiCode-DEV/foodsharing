<template>
  <b-alert show>
    <a :href="$url('forum', regionId)"><h4>{{ name }}</h4></a>
    <div class="regionTopClass">
      {{ $i18n('terminology.n_foodsaver', {count: foodSaverCount}) }},
      <span v-if="!isWorkGroup">{{ $i18n('terminology.n_home_regions', {count: foodSaverHomeDistrictCount}) }},</span>
      {{ $i18n('terminology.n_sleeping', {count: foodSaverHasSleepingHatCount}) }}

      <span v-if="!isWorkGroup">, {{ $i18n('terminology.n_ambassadors', {count: ambassadorCount}) }},
        {{ $i18n('terminology.n_stores', {count: storesCount}) }},
        {{ $i18n('terminology.n_cooperating_stores', {count: storesCooperationCount}) }},
        {{ $i18n('statistics.kg_food_saved', {count: storesFetchedWeight}) }},
        {{ $i18n('statistics.num_fetches', {count: storesPickupsCount}) }}</span>
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

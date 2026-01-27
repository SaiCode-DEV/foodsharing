<template>
  <b-alert
    v-if="ancestors && denied"
    show
    variant="danger"
    dismissible
  >
    <i class="fas fa-bolt-lightning mr-2" />
    <span v-if="ancestors.length === 1" v-text="$t('region.denied.this_region')" />
    <span v-else v-text="$t('region.denied.some_group', ancestors[0])" />
    <span
      v-for="(ancestor, i) in ancestors.slice(1, ancestors.length - 1)"
      :key="ancestor.id"
      v-text="$t('region.denied.subgroup_in', { parent: ancestor.name, name: ancestors[i].name })"
    />
    <span
      v-if="ancestors.length > 1 && ancestors[ancestors.length - 1].type === 7"
      v-text="$t('region.denied.group_in_this_group', ancestors[ancestors.length - 2])"
    />
    <span
      v-else-if="ancestors.length > 1 && ancestors[ancestors.length - 1].type !== 7"
      v-text="$t('region.denied.group_in_this_region', ancestors[ancestors.length - 2])"
    />
  </b-alert>
</template>
<script>
import { getInaccessibleRegionRedirects } from '@/api/regions'
import { GET } from '@/browser'

export default {
  data: () => ({
    ancestors: null,
  }),
  computed: {
    denied () {
      return Number(GET('denied'))
    },
  },
  async created () {
    if (this.denied) {
      this.ancestors = await getInaccessibleRegionRedirects(this.denied)
    }
  },
}
</script>

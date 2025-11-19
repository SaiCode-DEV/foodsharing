<template>
  <container
    :title="$t('globals.type.regions_menu')"
    tag="regionSideNav"
    class="bg-white"
  >
    <NavRegionsLinkEntry
      :entry="regionMenu"
      :is-linking-subpages="isLinkingSubpages"
      @change-page="$emit('change-page', $event.valueOf())"
    />
  </container>
</template>
<script>
import NavRegionsLinkEntry from '@/components/Navigation/Regions/NavRegionsLinkEntry.vue'
import Container from '@/components/Container/Container.vue'

export default {
  components: { NavRegionsLinkEntry, Container },
  props: {
    isWorkGroup: { type: Boolean, required: true },
    regionMenu: { type: Object, required: true },
    /**
     * If true, links to sub-pages will be actual links that reload the page. If false, links will make this component
     * emit a 'change-page' event.
     */
    isLinkingSubpages: { type: Boolean, required: true },
  },
}
</script>

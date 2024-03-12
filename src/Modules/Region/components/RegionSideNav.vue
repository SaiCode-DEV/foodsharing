<template>
  <container
    :title="$i18n('globals.type.regions_menu')"
    class="bg-white"
  >
    <NavRegionsLinkEntry :entry="regionMenu" />
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
  },
}
</script>

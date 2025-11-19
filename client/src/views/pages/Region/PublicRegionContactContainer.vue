<template>
  <Container
    :title="$t('menu.entry.contact')"
    tag="publicRegionContacts"
    wrap-content
  >
    <span v-if="props.regionData.hasAmbassador">
      <i class="fas fa-envelope mr-2" />
      <a :href="$url('mailto_mail_foodsharing_network', props.regionData.email)" v-text="mailText(props.regionData.email)" />
    </span>
    <span v-else v-text="$t('content.communities.noAmbassador')" />
    <!-- TODO show ambassadors to logged in users -->
  </Container>
</template>
<script setup>
import { defineProps } from 'vue'
import Container from '@/components/Container/Container.vue'
import { url } from '@/helper/urls'

const props = defineProps({
  regionData: { type: Object, required: true },
})

function mailText (email) {
  // insert no-width-spaces to allow line breaks at the right places
  return url('mail_foodsharing_network', email).replaceAll(/([.-_@])/g, '\u200B$1')
}
</script>

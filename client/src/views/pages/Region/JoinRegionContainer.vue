<template>
  <Container
    :title="$i18n('region.public.join_name', regionData)"
    tag="publicRegionJoin"
  >
    <div class="list-group-item" v-text="$i18n('region.public.join_text', regionData)" />
    <ContainerButton
      variant="success"
      text-key="region.public.join"
      icon="fas fa-plus"
      :disabled="loading"
      @click="join"
    />
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import { joinRegion } from '@/api/regions'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

export default {
  components: { Container, ContainerButton },
  mixins: [ConfirmationDialogue],
  props: {
    regionData: { type: Object, required: true },
  },
  data: () => ({
    loading: false,
  }),
  methods: {
    async join () {
      if (!await this.confirmationDialogue('region.public.confirm_entering', {
        okTitle: this.$i18n('region.public.join'),
        okVariant: undefined,
        params: { name: this.regionData.name },
      })) return
      this.loading = true
      await joinRegion(this.regionData.id)
      const locationWithoutDenied = location.href.substring(location.origin.length).replace(/&?denied=\d+/, '')
      location.href = this.$url('relogin_and_redirect_to_url', locationWithoutDenied)
    },
  },
}
</script>

<template>
  <div>
    <Container
      :title="$i18n('region.public.leave_name', { name })"
      tag="publicRegionLeave"
      :container-is-expanded="false"
    >
      <div class="list-group-item" v-text="$i18n('region.public.leave_text', { name })" />
      <ContainerButton
        variant="danger"
        text-key="region.public.leave"
        icon="fas fa-user-slash"
        :disabled="loading"
        @click="removeMeFromRegion"
      />
    </Container>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import { leaveRegion } from '@/api/regions'
import { HTTP_RESPONSE } from '@/consts'
import { pulseError } from '@/script'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()
export default {
  components: { Container, ContainerButton },
  props: {
    regionId: { type: Number, required: true },
    name: { type: String, required: true },
    isWorkGroup: { type: Boolean, default: false },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  computed: {
    translationKey () {
      switch (true) {
        case this.isWorkGroup:
          return 'group.quit_name_workgroup'
        case this.isHomeRegion:
          return 'group.quitting_home_district_warning'
        default:
          return 'group.really_quit_district'
      }
    },
    isHomeRegion () {
      return userStore.getHomeRegion === this.regionId
    },
  },
  methods: {
    async removeMeFromRegion () {
      const confirmed = await this.confirmationDialogue(this.translationKey, {
        title: this.$i18n('are_you_sure'),
        okTitle: this.$i18n('button.yes_i_am_sure'),
        okVariant: 'danger',
        params: { name: this.name },
        countdown: this.isHomeRegion ? 30 : 5,
      })

      if (!confirmed) return

      try {
        this.loading = true
        await leaveRegion(this.regionId)
        const redirectLocation = location.href.substring(location.origin.length)
        location.href = this.$url('relogin_and_redirect_to_url', redirectLocation)
      } catch (err) {
        if (err.code && err.code === HTTP_RESPONSE.CONFLICT) {
          pulseError(this.$i18n('region.store_managers_cannot_leave'))
        } else {
          pulseError(this.$i18n('error_unexpected'))
          throw err
        }
      }
    },
  },
}
</script>

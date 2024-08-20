<template>
  <div>
    <Container
      :title="$i18n('group.quit')"
      class="bg-white"
    >
      <p class="p-2">
        <b-link @click="$refs['remove-region-modal'].show()">
          {{ isWorkGroup ? $i18n('group.quit_name_workgroup', { name: name }) : $i18n('group.quit_name_district', { name: name }) }}
        </b-link>
      </p>
    </Container>
    <b-modal
      ref="remove-region-modal"
      :title="$i18n('are_you_sure')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      :cancel-title="$i18n('button.cancel')"
      ok-variant="danger"
      @ok="removeMeFromRegion"
    >
      {{ $i18n(translationKey, { name: name }) }}
    </b-modal>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import { leaveRegion } from '@/api/regions'
import { HTTP_RESPONSE } from '@/consts'
import { pulseError } from '@/script'

export default {
  components: { Container },
  props: {
    regionId: { type: Number, required: true },
    name: { type: String, required: true },
    isWorkGroup: { type: Boolean, required: true },
    isHomeDistrict: { type: Boolean, required: true },
  },
  computed: {
    translationKey () {
      switch (true) {
        case this.isWorkGroup:
          return 'group.quit_name_workgroup'
        case this.isHomeDistrict:
          return 'group.quitting_home_district_warning'
        default:
          return 'group.really_quit_district'
      }
    },
  },
  methods: {
    async removeMeFromRegion () {
      try {
        await leaveRegion(this.regionId)
        window.location.href = this.$url('dashboard')
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

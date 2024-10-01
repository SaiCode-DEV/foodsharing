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
      @show="startCountdown"
      @ok="removeMeFromRegion"
    >
      {{ $i18n(translationKey, { name: name }) }}
      <template #modal-footer="{ ok, cancel }">
        <b-button @click="cancel()">
          {{ $i18n('button.cancel') }}
        </b-button>
        <div>
          <b-button
            :disabled="countdown > 0"
            variant="danger"
            @click="ok()"
          >
            {{ $i18n('button.yes_i_am_sure') }}
          </b-button>

          <div v-if="countdown > 0" class="delete-countdown">
            {{ $i18n('button.countdown_clickable', { countdown }) }}
          </div>
        </div>
      </template>
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
  data () {
    return {
      countdown: 5,
      interval: null,
    }
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
    startCountdown () {
      if (this.interval) {
        clearInterval(this.interval)
      }
      if (this.isHomeDistrict) {
        this.countdown = 30
      } else {
        this.countdown = 5
      }
      this.interval = setInterval(() => {
        if (this.countdown <= 0) {
          clearInterval(this.interval)
        }
        this.countdown--
      }, 1000)
    },
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

<style>
.delete-countdown {
  display: block;
  font-size: 80%;
  color: var(--fs-color-danger-500);
  height: 0;
  text-align: center;
}
</style>

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
        @click="$refs['remove-region-modal'].show()"
      />
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
  data () {
    return {
      countdown: 5,
      interval: null,
      loading: false,
    }
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
    startCountdown () {
      if (this.interval) {
        clearInterval(this.interval)
      }
      if (this.isHomeRegion) {
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

<style>
.delete-countdown {
  display: block;
  font-size: 80%;
  color: var(--fs-color-danger-500);
  height: 0;
  text-align: center;
}
</style>

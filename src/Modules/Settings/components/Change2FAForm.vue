<template>
  <div>
    <p class="col-sm-auto">
      {{ $i18n('settings.2fa.intro') }}
    </p>
    <p class="col-sm-auto">
      {{ $i18n('settings.2fa.explanation') }}
    </p>
    <p class="col-sm-auto">
      {{ $i18n('settings.2fa.further_info.intro') }}
      <a :href="$i18n('settings.2fa.further_info.link')" target="_blank">{{ $i18n('settings.2fa.further_info.text') }}</a>.
    </p>

    <p class="col-sm-auto">
      {{ $i18n('settings.2fa.status_is') }}:
      <span v-if="totpActive" style="color: green; font-weight: bold;">{{ $i18n('settings.2fa.active') }}.</span>
      <span v-else style="color: red; font-weight: bold;">{{ $i18n('settings.2fa.inactive') }}.</span>
      {{ $i18n('settings.2fa.password_required') }}
    </p>

    <p v-if="totpActive" class="col-sm-auto">
      {{ $i18n('settings.2fa.num_backup_codes') }}:
      <span id="testing-num-backup-codes">
        {{ numBackupCodes }}
      </span>
    </p>

    <p class="col-sm-auto">
      <b-button
        v-if="totpActive"
        variant="danger"
        :disabled="isLoading"
        class="testing-totp-disable"
        @click="$refs.TwoFADisableModal.show()"
        v-text="$i18n('settings.2fa.action_label_remove')"
      />
      <b-button
        v-else
        variant="success"
        :disabled="isLoading"
        class="testing-totp-enable"
        @click="get2FAsecret"
        v-text="$i18n('settings.2fa.action_label_setup')"
      />
    </p>
    <TwoFAEnable
      ref="TwoFAEnableModal"
      :secret="modalData.secret"
      :qr-code="modalData.qrCode"
      :backup-codes="modalData.backupCodes"
    />
    <TwoFADisable
      ref="TwoFADisableModal"
    />
  </div>
</template>

<script>
import TwoFAEnable from './TwoFAEnableModal.vue'
import TwoFADisable from './TwoFADisableModal.vue'
import { pulseError } from '@/script'
import { get2FAdata } from '@/api/settings'

export default {
  components: { TwoFAEnable, TwoFADisable },
  props: {
    totpActive: { type: Boolean, required: true },
    numBackupCodes: { type: Number, required: true },
  },
  data () {
    return {
      isLoading: false,
      modalData: {
        secret: null,
        qrCode: null,
        backupCodes: [],
      },
    }
  },
  methods: {
    async get2FAsecret () {
      this.isLoading = true

      try {
        const data = await get2FAdata()
        // Pass data to modal
        this.modalData.secret = data.secret
        this.modalData.qrCode = data.qrCode
        this.modalData.backupCodes = data.backupCodes
        // Show modal
        this.$refs.TwoFAEnableModal.show()
      } catch (e) {
        pulseError(e.message)
      } finally {
        this.isLoading = false
      }
    },
  },
}
</script>

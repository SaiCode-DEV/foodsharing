<template>
  <div class="m-2">
    <b-alert variant="info" show>
      <div v-if="hasLocalMediationGroup && mediationGroupEmail">
        {{ $t('profile.mediation.info') }}
        <a :href="$url('mailto_mail_foodsharing_network', mediationGroupEmail)">
          {{ $url('mail_foodsharing_network', mediationGroupEmail) }}
        </a>
      </div>
      <div v-else>
        {{ $t('profile.mediation.no_group') }}
      </div>
    </b-alert>
  </div>
</template>

<script>
export default {
  props: {
    mediationGroupEmail: { type: String, required: true },
    hasLocalMediationGroup: { type: Boolean, default: false },
  },
}
</script>

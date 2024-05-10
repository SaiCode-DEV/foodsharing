<template>
  <div class="bootstrap">
    <b-alert variant="danger" show>
      <h3>{{ $i18n('profile.warning') }}</h3>
      <div v-if="bounceWarning.mayRemove">
        {{ $i18n('profile.mail_bounce.warning_others', { email: bounceWarning.emailAddress }) }}
      </div>
      <div v-else>
        {{ $i18n('profile.mail_bounce.warning_1', { email: bounceWarning.emailAddress }) }}
        <a href="/?page=settings"> {{ $i18n('profile.mail_bounce.warning_2') }} </a>
        {{ $i18n('profile.mail_bounce.warning_3') }}
        <a href="https://foodsharing.freshdesk.com/support/solutions/articles/77000299947-e-mail-sperre-im-profil"> {{ $i18n('profile.mail_bounce.warning_4') }}</a>
      </div>
    </b-alert>

    <div v-if="bounceWarning.mayRemove">
      <ul>
        <li
          v-for="event in bounceWarning.bounceEvents"
          :key="event.date"
        >
          {{ $dateFormatter.date(convertDate(event.date)) }}: "{{ event.category }}"
        </li>
      </ul>
      <b-button @click.prevent="removeBounces()">
        {{ $i18n('profile.mail_bounce.remove_button') }}
      </b-button>
    </div>
  </div>
</template>

<script>
import { BAlert, BButton } from 'bootstrap-vue'
import { hideLoader, pulseError, reload, showLoader } from '@/script'
import { removeUserFromBounceList } from '@/api/profile'
import i18n from '@/helper/i18n'

export default {
  components: { BAlert, BButton },
  props: { bounceWarning: { type: Array, required: true } },
  methods: {
    convertDate (date) {
      return new Date(Date.parse(date))
    },
    async removeBounces () {
      showLoader()
      try {
        await removeUserFromBounceList(this.userId)
        reload()
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

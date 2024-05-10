<template>
  <div class="container bg-white">
    <ul>
      <li v-if="profileInfos.maySeeLastActivity" class="mb-2">
        <b>{{ $i18n('profile.infos.lastActivity') }}:</b> {{ lastActivityText }}
      </li>
      <li v-if="profileInfos.registrationDate" class="mb-2">
        <b>{{ $i18n('profile.infos.registrationDate') }}:</b> {{ profileInfos.registrationDate }}
      </li>
      <li v-if="profileInfos.privateMail" class="mb-2">
        <b>{{ $i18n('profile.infos.privateMail') }}:</b>
        <p><a :href="getMailboxUrl(profileInfos.privateMail)">{{ splitMail(profileInfos.privateMail)[0] }}@<wbr>{{ splitMail(profileInfos.privateMail)[1] }}</a></p>
      </li>
      <li v-if="profileInfos.fsMail" class="mb-2">
        <b>{{ $i18n('profile.infos.fsMail') }}:</b>
        <p><a :href="getMailboxUrl(profileInfos.fsMail)">{{ splitMail(profileInfos.fsMail)[0] }}@<wbr>{{ splitMail(profileInfos.fsMail)[1] }}</a></p>
      </li>
      <li class="mb-2">
        <b>{{ $i18n('profile.infos.buddies') }}:</b>
        <p>{{ buddycountTranslation }}</p>
      </li>
      <li>
        <b>{{ getFsIdTranslation }}:</b> {{ profileInfos.fsId }}
      </li>
    </ul>
  </div>
</template>

<script>

import { ROLE } from '@/consts'

export default {
  props: { profileInfos: { type: Object, required: true } },
  computed: {
    isFoodSaver () {
      return this.profileInfos.role > ROLE.FOODSHARER
    },
    lastActivityText () {
      return this.profileInfos.lastActivity ? this.profileInfos.lastActivity : this.$i18n('profile.infos.never')
    },
    getFsIdTranslation () {
      return !this.isFoodSaver ? this.$i18n('profile.infos.foodsharerId') : this.$i18n('profile.infos.foodsaverId')
    },
    buddycountTranslation () {
      const knownBuddycount = this.$i18n('profile.infos.buddycount_known', { name: this.profileInfos.name, count: this.profileInfos.buddyCount })
      const followedBuddycount = this.$i18n('profile.infos.buddycount_followed', { name: this.profileInfos.name, count: this.profileInfos.buddyCount })
      return this.profileInfos.buddyCount > 1 ? knownBuddycount : followedBuddycount
    },
  },
  methods: {
    getMailboxUrl (mail) {
      let mailboxUrl = this.$url('mailboxMailto', mail)
      if (this.profileInfos.fsIdSession === this.profileInfos.fsId && mail.includes('foodsharing.network')) {
        mailboxUrl = this.$url('mailbox')
      }
      return mailboxUrl
    },
    splitMail (mail) {
      return mail.split('@')
    },
  },
}
</script>

<style lang="scss" scoped>
li {
  list-style: none;
}
</style>

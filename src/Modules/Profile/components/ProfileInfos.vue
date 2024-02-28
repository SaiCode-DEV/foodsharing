<template>
  <div class="infos">
    <ul>
      <li v-if="maySeeLastActivity" class="mb-2">
        <b>{{ $i18n('profile.infos.lastActivity') }}:</b> {{ lastActivityText }}
      </li>
      <li v-if="registrationDate" class="mb-2">
        <b>{{ $i18n('profile.infos.registrationDate') }}:</b> {{ registrationDate }}
      </li>
      <li v-if="privateMail" class="mb-2">
        <b>{{ $i18n('profile.infos.privateMail') }}:</b>
        <p><a :href="getMailboxUrl(privateMail)">{{ splitMail(privateMail)[0] }}@<wbr>{{ splitMail(privateMail)[1] }}</a></p>
      </li>
      <li v-if="fsMail" class="mb-2">
        <b>{{ $i18n('profile.infos.fsMail') }}:</b>
        <p><a :href="getMailboxUrl(fsMail)">{{ splitMail(fsMail)[0] }}@<wbr>{{ splitMail(fsMail)[1] }}</a></p>
      </li>
      <li class="mb-2">
        <b>{{ $i18n('profile.infos.buddies') }}:</b>
        <p>{{ buddycountTranslation }}</p>
      </li>
      <li>
        <b>{{ getFsIdTranslation }}:</b> {{ fsId }}
      </li>
    </ul>
  </div>
</template>

<script>

export default {
  props: {
    maySeeLastActivity: { type: Boolean, default: false },
    lastActivity: { type: String, default: '' },
    registrationDate: { type: String, default: '' },
    privateMail: { type: String, default: null },
    fsMail: { type: String, default: '' },
    buddyCount: { type: Number, default: 0 },
    name: { type: String, default: '' },
    fsId: { type: Number, required: true },
    fsIdSession: { type: Number, required: true },
    isfoodsaver: { type: Boolean, default: false },
  },
  computed: {
    lastActivityText () {
      return this.lastActivity ? this.lastActivity : this.$i18n('profile.infos.never')
    },
    getFsIdTranslation () {
      return !this.isfoodsaver ? this.$i18n('profile.infos.foodsharerId') : this.$i18n('profile.infos.foodsaverId')
    },
    buddycountTranslation () {
      const knownBuddycount = this.$i18n('profile.infos.buddycount_known', { name: this.name, count: this.buddyCount })
      const followedBuddycount = this.$i18n('profile.infos.buddycount_followed', { name: this.name, count: this.buddyCount })
      return this.buddyCount > 1 ? knownBuddycount : followedBuddycount
    },
  },
  methods: {
    getMailboxUrl (mail) {
      let mailboxUrl = this.$url('mailboxMailto', mail)
      if (this.fsIdSession === this.fsId && mail.includes('foodsharing.network')) {
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

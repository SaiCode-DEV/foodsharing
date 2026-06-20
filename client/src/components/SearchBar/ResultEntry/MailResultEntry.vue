<template>
  <a
    :href="$url('mailbox', null, mail.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="mail.folder === 1"
          v-b-tooltip.noninteractive="$t('search.results.mail.recieved_tooltip')"
          class="fas fa-reply"
        />
        <i
          v-if="mail.folder === 2"
          v-b-tooltip.noninteractive="$t('search.results.mail.sent_tooltip')"
          class="fas fa-share"
        />
        {{ mail.name }}
        <i
          v-if="mail.hasAttachments"
          v-b-tooltip.noninteractive="$t('search.results.mail.attachment_tooltip')"
          class="fas fa-paperclip ml-1"
        />
      </h6>
      <br>
      <small class="separate">
        <span v-text="fromTo" />
        <TimeDisplay
          :time="mail.sentAt"
          plain
          :tooltip="null"
        />
      </small>
    </div>
  </a>
</template>
<script>
import TimeDisplay from '@/components/TimeDisplay.vue'

export default {
  components: { TimeDisplay },
  props: {
    mail: {
      type: Object,
      required: true,
    },
  },
  computed: {
    fromTo () {
      const from = this.mailDisplay(this.mail.senderName, this.mail.senderMail)
      const to = this.mailDisplay(this.mail.recipientName, this.mail.recipientMail)
      const other = this.mail.recipientCount - 1
      if (other) {
        return this.$t('search.results.mail.from_to_many', { from, to, other })
      }
      return this.$t('search.results.mail.from_to', { from, to })
    },
  },
  methods: {
    mailDisplay (name, mail) {
      mail ??= ''
      if (!name || name === mail || name === 'null') {
        return mail
      }
      return this.$t('search.results.mail.mail_name', { mail, name })
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: ' • ';
}
</style>

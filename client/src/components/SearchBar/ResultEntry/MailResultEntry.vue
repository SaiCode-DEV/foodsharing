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
          v-if="mail.has_attachments"
          v-b-tooltip.noninteractive="$t('search.results.mail.attachment_tooltip')"
          class="fas fa-paperclip ml-1"
        />
      </h6>
      <br>
      <small class="separate">
        <span v-text="fromTo" />
        <Time
          :time="mail.time"
          plain
          :tooltip="null"
        />
      </small>
    </div>
  </a>
</template>
<script>
import Time from '@/components/Time.vue'

export default {
  components: { Time },
  props: {
    mail: {
      type: Object,
      required: true,
    },
  },
  computed: {
    fromTo () {
      const from = this.mailDisplay(this.mail.sender_name, this.mail.sender_mail)
      const to = this.mailDisplay(this.mail.recipient_name, this.mail.recipient_mail)
      const other = this.mail.recipient_count - 1
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

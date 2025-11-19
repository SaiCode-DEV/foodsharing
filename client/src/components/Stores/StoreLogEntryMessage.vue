<template>
  <!-- eslint-disable vue/no-v-html -->
  <!-- Only translation content -->
  <span v-html="actionText(action)" />
  <!-- eslint-enable -->
</template>

<script>
const ACTION_TYPES_WITH_OPTIONAL_REASON = [13]

export default {
  props: {
    action: { type: Object, required: true },
  },
  methods: {
    actionText (action) {
      const params = {
        actor: this.userLinkHtml(action.acting_foodsaver),
        target: this.userLinkHtml(action.affected_foodsaver),
        date: this.$dateFormatter.format(action.date_reference),
      }
      const reason = (action.reason && ACTION_TYPES_WITH_OPTIONAL_REASON.includes(action.action_id)) ? '_with_reason' : ''
      return this.$t(`store.log.message.${action.action_id}${reason}`, params)
    },
    userLinkHtml (user) {
      if (!user?.id) return ''
      if (!user.name) return this.$t('forum.deleted_user')
      return `<a href="${this.$url('profile', user.id)}">${user.name}</a>`
    },
  },
}
</script>

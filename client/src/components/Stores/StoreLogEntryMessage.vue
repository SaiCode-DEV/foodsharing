<template>
  <!-- eslint-disable vue/no-v-html -->
  <!-- Only translation content -->
  <span v-html="actionText(action)" />
  <!-- eslint-enable -->
</template>

<script>
import { ACTION_TYPES_WITH_OPTIONAL_REASON, ACTION_TYPES_WITH_JSON_CONTENT } from './StoreLogActions'

export default {
  props: {
    action: { type: Object, required: true },
  },
  methods: {
    actionText (action) {
      const params = {
        actor: this.userLinkHtml(action.actor),
        target: this.userLinkHtml(action.target),
        date: this.$dateFormatter.format(action.dateReference),
      }
      if (ACTION_TYPES_WITH_JSON_CONTENT.includes(action.actionType)) {
        Object.assign(params, JSON.parse(action.content))
      }
      const reason = (action.reason && ACTION_TYPES_WITH_OPTIONAL_REASON.includes(action.actionType)) ? '_with_reason' : ''
      return this.$t(`store.log.message.${action.actionType}${reason}`, params)
    },
    userLinkHtml (user) {
      if (!user?.id) return ''
      if (!user.name) return this.$t('forum.deleted_user')
      return `<a href="${this.$url('profile', user.id)}">${user.name}</a>`
    },
  },
}
</script>

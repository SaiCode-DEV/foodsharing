<template>
  <span v-html="actionText(action)" />
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
        actor: `<a href="${this.$url('profile', action.acting_foodsaver.id)}">${action.acting_foodsaver.name}</a>`,
        target: `<a href="${this.$url('profile', action.affected_foodsaver?.id)}">${action.affected_foodsaver?.name}</a>`,
        date: this.$dateFormatter.format(action.date_reference),
      }
      const reason = (action.reason && ACTION_TYPES_WITH_OPTIONAL_REASON.includes(action.action_id)) ? '_with_reason' : ''
      return this.$i18n(`store.log.message.${action.action_id}${reason}`, params)
    },
  },
}
</script>

import { pulseSuccess } from '@/script'

export default {
  methods: {
    async copyToClipboard (text, messageKey = 'copied_to_clipboard', params = {}) {
      if (navigator.clipboard) {
        await navigator.clipboard.writeText(text)
        if (messageKey) {
          pulseSuccess(this.$t(messageKey, { text, ...params }))
        }
      }
    },
  },
}

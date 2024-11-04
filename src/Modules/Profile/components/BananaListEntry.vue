<template>
  <div class="d-flex my-1 py-2">
    <Avatar
      :user="user"
      :size="50"
      class="mt-1 pr-2 pt-1"
    />
    <div>
      <div class="time p-1">
        <a :href="$url('profile', user.id)">
          {{ user.name }}
        </a>
        <i class="fas fa-fw fa-angle-right" />
        {{ $dateFormatter.date(when) }}
        <a
          v-if="canRemove"
          href="#"
          :title="$i18n('profile.banana.remove.confirm_title')"
          @click="removeBanana"
        ><i class="fas fa-trash" />
        </a>
      </div>
      <!-- For whitespace and layout reasons, the text needs to be enclosed directly: -->
      <!-- eslint-disable-next-line vue/singleline-html-element-content-newline -->
      <div class="msg ml-1 p-1 pl-2">{{ text }}</div>
    </div>
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import { deleteBanana } from '@/api/banana'
import { hideLoader, pulseError, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

export default {
  components: { Avatar },
  mixins: [ConfirmationDialogue],
  props: {
    recipientId: { type: Number, required: true },
    user: { type: Object, required: true },
    createdAt: { type: String, required: true },
    text: { type: String, default: '' },
    canRemove: { type: Boolean, default: false },
    isSent: { type: Boolean, default: false },
  },
  data () {
    return {
      when: new Date(Date.parse(this.createdAt)),
    }
  },
  methods: {
    async removeBanana () {
      if (!await this.confirmationDialogue('profile.banana.remove.confirm_message')) return
      showLoader()
      try {
        if (this.isSent) {
          await deleteBanana(this.user.id, this.recipientId)
        } else {
          await deleteBanana(this.recipientId, this.user.id)
        }
        location.reload()
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

<style lang="scss" scoped>
.msg {
  white-space: pre-line;
  border-left: 3px solid var(--fs-border-default);
}

.time a {
  color: var(--fs-color-secondary-500);
  font-weight: bolder;
}
</style>

<template>
  <div class="banana-container d-flex my-1 py-2">
    <a
      v-b-tooltip.hover="$i18n('profile.go')"
      :href="$url('profile', authorId)"
    >
      <Avatar
        :url="avatar"
        :size="50"
        class="member-pic mt-1 pr-2 pt-1"
        :auto-scale="false"
      />
    </a>
    <div>
      <div class="time p-1">
        <a :href="$url('profile', authorId)">
          {{ authorName }}
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
import Avatar from '@/components/Avatar'
import { deleteBanana } from '@/api/profile'
import { hideLoader, pulseError, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

export default {
  components: { Avatar },
  mixins: [ConfirmationDialogue],
  props: {
    recipientId: { type: Number, required: true },
    authorId: { type: Number, required: true },
    authorName: { type: String, default: '' },
    avatar: { type: String, default: '' },
    createdAt: { type: String, required: true },
    text: { type: String, default: '' },
    canRemove: { type: Boolean, default: false },
  },
  data () {
    return {
      when: new Date(Date.parse(this.createdAt)),
    }
  },
  methods: {
    async removeBanana () {
      // the banana dialog has to be closed because the confirm dialog would appear behind it
      this.$emit('close-dialog')

      if (!await this.confirmationDialogue('profile.banana.remove.confirm_message')) return
      showLoader()
      try {
        await deleteBanana(this.recipientId, this.authorId)
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
.banana-container {
  border-top: 1px solid var(--fs-border-default);

  .member-pic ::v-deep img {
    width: 50px;
    height: 50px;
  }

  .msg {
    white-space: pre-line;
    border-left: 3px solid var(--fs-border-default);
  }

  .time a {
    color: var(--fs-color-secondary-500);
    font-weight: bolder;
  }
}
</style>

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

export default {
  components: { Avatar },
  props: {
    recipientId: { type: Number, required: true },
    user: { type: Object, required: true },
    createdAt: { type: String, required: true },
    text: { type: String, default: '' },
    canRemove: { type: Boolean, default: false },
  },
  computed: {
    when () {
      return new Date(Date.parse(this.createdAt))
    },
  },
  methods: {
    removeBanana () {
      this.$emit('remove-banana', this.key, this.user.id, this.recipientId)
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

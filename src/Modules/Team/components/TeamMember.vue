<template>
  <div class="m-2">
    <div class="d-flex justify-content-center">
      <Avatar
        :user="{ avatar: member.photo }"
        shape="round"
        :size="150"
      />
    </div>
    <div class="pt-4">
      <p v-if="showName" class="nameClass">
        {{ member.name }}
      </p>
      <p>{{ member.position }}</p>
      <Markdown :source="member.about_me_public" :classes="isLineClampClass" />
    </div>
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
export default {
  name: 'TeamMember',
  components: { Avatar, Markdown },
  props: {
    member: { type: Object, default: null },
    isLineClamp: { type: Boolean, default: false },
    showName: { type: Boolean, default: true },
  },
  computed: {
    isLineClampClass () {
      return this.isLineClamp ? 'line-clamp' : ''
    },
  },
}
</script>

<style scoped lang="scss">
.line-clamp {
  display: -webkit-box;
  -webkit-line-clamp: 6;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.markdown {
  white-space: pre-line;
}
.nameClass {
  font-weight: bold;
  font-size: large;
}
</style>

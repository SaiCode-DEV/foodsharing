<template>
  <div class="member-container" :class="{ 'line-clamp': isClamp }">
    <div class="avatar-section">
      <Avatar
        :user="{ avatar: member.photo }"
        shape="round"
        :size="150"
      />
    </div>
    <div class="content-section">
      <p v-if="showName" class="nameClass">
        {{ member.name }}
      </p>
      <p>{{ member.position }}</p>
      <hr class="divider mt-0 mb-3">
      <div class="markdown-wrapper">
        <Markdown :source="member.aboutMePublic" />
      </div>
    </div>
  </div>
</template>

<script setup>
import Avatar from '@/components/Avatar/Avatar.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import { defineProps } from 'vue'

defineProps({
  member: { type: Object, default: null },
  isClamp: { type: Boolean, default: false },
  showName: { type: Boolean, default: true },
})
</script>

<style scoped lang="scss">
.member-container {
  display: flex;
  flex-direction: column;
  padding: 0.5rem;
  position: relative;

  &.line-clamp {
    max-height: 30rem;
    overflow: hidden;
    &::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 69px;
      background: linear-gradient(transparent, var(--fs-color-light));
      pointer-events: none;
    }
  }
  .avatar-section {
    flex: 0 0 auto;
    display: flex;
    justify-content: center;
    margin-bottom: 1rem;
  }
  .content-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
  }
}

.markdown-wrapper {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.markdown {
  white-space: pre-line;
}

.nameClass {
  font-weight: bold;
  font-size: large;
}

.divider {
  display: block;
  flex: 1 1 100%;
  height: 0px;
  max-height: 0px;
  transition: inherit;
}
</style>

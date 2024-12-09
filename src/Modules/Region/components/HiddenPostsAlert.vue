<template>
  <b-alert show>
    {{ $i18n('thread.hiddenPostsInfo.includesHidden') }}
    <span v-if="props.isModerator" v-text="$i18n('thread.hiddenPostsInfo.cannotSee')" />
    <b-form-checkbox v-model="localShowHiddenPosts" switch>
      {{ $i18n('thread.hiddenPostsInfo.toggle') }}
    </b-form-checkbox>
  </b-alert>
</template>

<script setup>
import { defineProps, defineEmits, computed } from 'vue'

const emit = defineEmits(['update:showHiddenPosts'])
const props = defineProps({
  isModerator: { type: Boolean, default: false },
  showHiddenPosts: { type: Boolean, default: false },
})

const localShowHiddenPosts = computed({
  get: () => props.showHiddenPosts,
  set: (value) => emit('update:showHiddenPosts', value),
})
</script>

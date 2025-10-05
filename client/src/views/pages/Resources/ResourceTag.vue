<template>
  <span class="resource-tag d-inline-block mx-2 mb-2 text-nowrap mw-100" @click="$emit('open', resource)">
    <Avatar
      :user="resource.user"
      :size="35"
      href=""
      shape="round"
    />
    <b-badge
      :style="{ backgroundColor: color }"
      class="cutoff-badge"
      size="lg"
      pill
    >
      <span>
        <i v-if="resource.isPrivate" class="fas fa-user-friends" />
        <span
          ref="nameSpan"
          v-text="resource.name"
        />
        <i
          v-if="resource.isFavorite"
          class="fas fa-star"
          style="color: var(--fs-color-warning-500)"
        />
      </span>
    </b-badge>
  </span>
</template>
<script setup>
import Avatar from '@/components/Avatar/Avatar.vue'
import { defineProps, computed, ref, onMounted } from 'vue'

const props = defineProps({
  resource: { type: Object, required: true },
})

function seededRandom (seed) {
  const x = Math.sin(seed + 1) * 1e4
  return x - Math.floor(x)
}

const nameSpan = ref()
onMounted(() => {
  const contentWidth = nameSpan.value.parentElement.clientWidth
  const availableWidth = nameSpan.value.parentElement.parentElement.clientWidth - 32 // parent width without padding

  if (contentWidth <= availableWidth) return

  const textWidth = nameSpan.value.clientWidth
  const availableTextWidth = availableWidth - contentWidth + textWidth

  nameSpan.value.style.setProperty('--scaling-factor', availableTextWidth / textWidth)
  nameSpan.value.style.setProperty('max-width', availableTextWidth + 'px')
})

const color = computed(() => {
  const hue = 60 + seededRandom(props.resource.id) * 100
  const saturation = 5 + props.resource.openness * 13 // Display open resources more saturated
  const lightness = 75 + seededRandom(props.resource.id + 2) * 5
  return `hsl(${hue}, ${saturation}%, ${lightness}%)`
})
</script>
<style scoped>
.cutoff-badge {
  margin-left: -1.5em;
  padding-left: 1.75em;
  font-size: 1em;
  vertical-align: middle;
  line-height: 1.3em;
  font-weight: 400;
  color: black;
  max-width: calc(100% - 18px); /* Make sure the resource doesn't extend past its parent. 18px resulting from avatar size, margins and paddings */
}
.cutoff-badge span {
  transform: scaleX(var(--scaling-factor));
  transform-origin: left;
  display: inline-block;
}
.resource-tag {
  cursor: pointer;
}
</style>

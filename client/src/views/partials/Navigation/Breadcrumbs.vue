<template>
  <ul class="d-flex bread">
    <li
      v-for="(item, i) in items"
      :key="i"
      :class="{ last: !item.href }"
    >
      <!-- the entry for the current page has no target and is only its text -->
      <router-link v-if="item.href" :to="item.href">
        {{ item.text }}
      </router-link>
      <template v-else>
        {{ item.text }}
      </template>
    </li>
  </ul>
</template>
<script setup>
defineProps({
  items: { type: Array, default: () => [] },
})
</script>
<style scoped lang="scss">
.bread {
  flex-wrap: wrap;
  padding-left: 2em;
  li:first-child {
    margin-left: -2em;
  }
}
</style>

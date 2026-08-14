<template>
  <ul class="d-flex bread">
    <li v-for="(item, i) in items" :key="i">
      <FsLink :to="item.href">
        {{ item.text }}
      </FsLink>
    </li>
    <!-- <li class="last">{{ item.name }}</li> -->
  </ul>
</template>
<script>
import FsLink from '@/components/UI/FsLink.vue'

export default {
  components: { FsLink },
  props: {
    items: { type: Array, default: () => [] },
  },
}
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

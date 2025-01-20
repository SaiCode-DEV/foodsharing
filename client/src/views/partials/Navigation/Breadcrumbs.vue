<template>
  <ul class="d-flex bread">
    <li v-for="(item, i) in items" :key="i">
      <a :href="item.href" v-text="item.text" />
    </li>
    <!-- <li class="last">{{ item.name }}</li> -->
  </ul>
</template>
<script>
export default {
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

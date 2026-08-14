<template>
  <Container
    v-if="children.length"
    :title="$t('region.public.children', { name, count: children.length })"
    tag="publicRegionChildren"
    :toggle-visiblity="children.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <router-link
      v-for="child in filteredList"
      :key="child.id"
      class="list-group-item dropdown-item dropdown-action"
      :to="$url('publicRegion', child.id)"
    >
      {{ child.name }}
    </router-link>
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import ListToggleMixin from '@/mixins/ContainerToggleMixin'

export default {
  components: { Container },
  mixins: [ListToggleMixin],
  props: {
    name: { type: String, required: true },
    children: { type: Array, default: () => [] },
  },
  mounted () {
    this.setList(this.children)
  },
}
</script>

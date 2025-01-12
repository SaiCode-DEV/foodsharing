<template>
  <Container
    v-if="children.length"
    :title="$i18n('region.public.children', { name, count: children.length })"
    tag="publicRegionChildren"
    :toggle-visiblity="children.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <a
      v-for="child in filteredList"
      :key="child.id"
      class="list-group-item dropdown-item dropdown-action"
      :href="$url('publicRegion', child.id)"
    >
      <a :href="$url('publicRegion', child.id)" v-text="child.name" />
    </a>
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

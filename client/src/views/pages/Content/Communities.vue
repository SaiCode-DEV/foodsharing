<template>
  <!-- eslint-disable vue/no-template-shadow -->
  <div>
    <Container
      :title="$i18n('content.communities.title')"
      :collapsible="false"
    >
      <div class="list-group-item">
        {{ $i18n('content.communities.text') }}
        <div class="alert alert-info mt-3">
          <i class="fas fa-info-circle mr-1" />
          {{ $i18n('content.communities.searchInfo') }}
        </div>
      </div>
    </Container>
    <Container
      v-for="region of rootRegion.children"
      :key="region.id"
      :title="region.name"
      :tag="region.name"
    >
      <div class="list-group-item">
        <h3><Region :region="region" /></h3>
        <div
          v-for="region of region.children"
          :key="region.id"
          class="px-3"
        >
          <h4><Region :region="region" /></h4>
          <RegionList :regions="region.children" />
        </div>
      </div>
    </Container>
  </div>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import Region from '@/components/Content/Communitites/Region.vue'
import RegionList from '@/components/Content/Communitites/RegionList.vue'

export default {
  components: { Region, RegionList, Container },
  props: {
    rootRegion: { type: Object, required: true },
  },
}
</script>

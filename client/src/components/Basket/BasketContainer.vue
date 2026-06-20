<template>
  <Container
    :title="$t('basket.by', { name: basket.creator.name } )"
    :collapsible="false"
    :wrap-contents="true"
    info-key="basket"
  >
    <div v-if="basket.pictures.length" class="list-group-item">
      <Gallery
        class="list-group-item"
        :images="basket.pictures"
        :height-in-px="350"
      />
    </div>
    <div class="list-group-item">
      <Markdown :source="basket.description" />
    </div>

    <div class="list-group-item times-section">
      <span v-for="key in timeKeys" :key="key">
        {{ $t(`basket.${key}`) }}
        <TimeDisplay
          :time="basket[key]"
          plain
          :tooltip="null"
        />
      </span>
    </div>
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import Gallery from '@/components/Images/Gallery.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import TimeDisplay from '@/components/TimeDisplay.vue'

export default {
  components: { Container, Gallery, Markdown, TimeDisplay },
  props: {
    basket: { type: Object, required: true },
  },
  computed: {
    timeKeys () {
      const keys = ['created', 'updated', 'until']
      if (!this.basket.updated) keys.splice(1, 1)
      return keys
    },
  },
}
</script>
<style scoped lang="scss">
.times-section {
  display: flex;
  justify-content: space-between;
  gap: 2em;
  font-size: smaller;
  .time {
    white-space: nowrap;
  }
}

</style>

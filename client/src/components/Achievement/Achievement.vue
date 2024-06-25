<template>
  <span class="achievement">
    <b-badge
      ref="badge"
      pill
      @click="$refs.detailsModal.show()"
    >
      <i :class="iconClass" />
      <span v-text="achievement.name" />
    </b-badge>
    <b-modal
      ref="detailsModal"
      centered
      ok-only
    >
      <template #modal-title>
        {{ $i18n('terminology.achievement') }}:
        <i :class="iconClass" />
        <span v-text="achievement.name" />
      </template>
      <Markdown :source="achievement.description" /><br>
      <i v-if="achievement.validityInDaysAfterAssignment" v-text="$i18n('achievements.validity.days', achievement)" />
      <i v-else v-text="$i18n('achievements.validity.indefinite')" />
    </b-modal>
  </span>
</template>
<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    achievement: { type: Object, required: true },
  },
  computed: {
    iconClass () {
      return (this.achievement.icon ?? 'fas fa-medal') + ' mr-0'
    },
  },
  methods: {
    showDetails () {
      console.log(this.achievement)
    },
  },
}
</script>
<style lang="scss" scoped>
.achievement {
  span {
    font-size: 1em;
    cursor: pointer;

    &:hover {
      background-color: var(--fs-color-success-600);
    }

    i {
      font-size: 1.3em;
      position: relative;
      top: 0.05em;
    }
  }

  &:not(:last-child){
    margin-right: 0.5em;
  }
}
</style>

<template>
  <Container
    :title="$t('quiz.plural')"
  >
    <a
      v-for="quiz in readableQuizzes"
      :key="quiz.id"
      class="list-group-item list-group-item-action"
      :class="{ disabled: quiz.id === selected }"
      :href="$url('quiz_admin_edit', quiz.id)"
    >
      <i
        v-b-tooltip="$t('quiz.editable_tooltip')"
        class="fas fa-fw fa-cog"
        :class="quiz.edit ? '' : 'invisible'"
      />
      {{ quiz.name }}
    </a>
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'

export default {
  components: { Container },
  props: {
    readableQuizzes: { type: Array, required: true },
    selected: { type: Number, default: 0 },
  },
}
</script>

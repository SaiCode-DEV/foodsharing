<template>
  <Container
    v-if="editedAchievement"
    :title="$i18n('achievements.edit', { name: achievement.name || $i18n('achievements.new')})"
    :collapsible="false"
  >
    <div class="list-group-item">
      <b-form-group :label="$i18n('achievements.editor.name')">
        <b-input v-model="editedAchievement.name" :state="editedAchievement.name.length ? null : false" />
      </b-form-group>

      <b-form-group :label="$i18n('achievements.editor.description')">
        <MarkdownInput
          :value.sync="editedAchievement.description"
          conceal-toolbar
          variant="outline-primary"
          :rows="2"
          :state="editedAchievement.description.length ? null : false"
        />
      </b-form-group>

      <b-form-group>
        <template #label>
          {{ $i18n('achievements.editor.icon') }}
          <Info info-key="achievementIcon" class="py-0" />
        </template>
        <b-input
          v-model="iconName"
          placeholder="tag"
          :formatter="(value) => value.replaceAll(' ', '')"
          :state="isIconValid"
        />
      </b-form-group>

      <b-form-group :label="$i18n('achievements.editor.validityInDaysAfterAssignment')">
        <b-input
          v-model="editedAchievement.validityInDaysAfterAssignment"
          :formatter="(value) => +value.replaceAll(/[^\d]/g, '') || NaN"
          type="number"
          :placeholder="$i18n('achievements.validity.indefinite')"
        />
      </b-form-group>
      {{ $i18n('achievements.editor.preview') }}
      <Achievement
        ref="preview"
        class="pl-3"
        :achievement="editedAchievement"
      />
    </div>
    <ContainerButton
      variant="success"
      text-key="globals.save_changes"
      icon="fas fa-save"
      :disabled="!isDataValid"
      @click="$emit('save', editedAchievement)"
    />
    <ContainerButton
      v-if="!isCreateMode"
      variant="danger"
      text-key="delete"
      icon="fas fa-trash"
      @click="deleteAchievement(achievement.id)"
    />
    <ContainerButton
      v-if="isCreateMode"
      variant="danger"
      text-key="button.cancel"
      icon="fas fa-times-circle"
      @click="$emit('cancel')"
    />
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import Achievement from '@/components/Achievement/Achievement.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import Info from '@/components/Help/Info.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

export default {
  components: { Container, Achievement, ContainerButton, MarkdownInput, Info },
  props: {
    achievement: { type: Object, default: null },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data () {
    return {
      editedAchievement: this.achievement,
      isIconValid: null,
    }
  },
  computed: {
    iconName: {
      get () {
        return this.editedAchievement?.icon?.replace(/^fas fa-/, '') ?? ''
      },
      set (icon) {
        this.editedAchievement.icon = icon ? `fas fa-${icon}` : null
        this.updateIconValidity()
      },
    },
    isCreateMode () {
      return !this.achievement.id
    },
    isDataValid () {
      return this.isIconValid && this.editedAchievement.name && this.editedAchievement.description
    },
  },
  watch: {
    achievement (achievement) {
      if (!achievement) {
        this.editedAchievement = null
        return
      }
      this.editedAchievement = Object.assign({}, achievement)
      this.updateIconValidity()
    },
  },
  methods: {
    async updateIconValidity () {
      await this.$nextTick()
      const preview = this.$refs.preview
      if (!preview) {
        this.isIconValid = null
        return
      }
      this.isIconValid = this.$refs.preview.$el.querySelector('i.fas').getBoundingClientRect().width > 0
    },
    async deleteAchievement (achievementId) {
      if (!await this.confirmationDialogue('achievements.deleteConfirmation')) return
      this.$emit('delete', achievementId)
    },
  },
}
</script>

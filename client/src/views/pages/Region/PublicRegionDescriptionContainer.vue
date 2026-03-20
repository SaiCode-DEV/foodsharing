<template>
  <Container
    v-if="description || mayEdit"
    :title="$t('region.public.description')"
    tag="publicRegionDescription"
  >
    <template v-if="!editMode" #options>
      <OverflowMenu icon="cog" :options="options" />
    </template>
    <div v-if="!editMode" class="list-group-item">
      <Markdown v-if="description" :source="description" />
      <b-alert
        v-else
        show
        class="m-0"
      >
        <i class="fas fa-edit mr-2" />
        {{ $t('region.public.desc.missing') }}
      </b-alert>
    </div>
    <div v-else>
      <MarkdownInput
        :value.sync="editDescription"
        :region-id="regionId"
        variant="outline-primary"
        sharp-edged
      />
    </div>
    <template v-if="editMode">
      <ContainerButton
        variant="danger"
        text-key="button.cancel"
        icon="fas fa-times"
        :disabled="loading"
        @click="cancel"
      />
      <ContainerButton
        variant="success"
        text-key="button.save"
        icon="fas fa-save"
        :disabled="loading"
        @click="save"
      />
    </template>
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { setPublicRegionData } from '@/api/regions'
import { pulseError } from '@/script'
import { useRegionStore } from '@/stores/regions'
import OverflowMenu from '@/components/OverflowMenu.vue'

const regionStore = useRegionStore()
export default {
  components: { Container, Markdown, MarkdownInput, ContainerButton, OverflowMenu },
  props: {
    regionId: { type: Number, required: true },
    description: { type: String, required: true },
    mayEdit: { type: Boolean, default: false },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data: () => ({
    editDescription: '',
    editMode: false,
    loading: false,
  }),
  computed: {
    isDescriptionChanged () {
      return this.editDescription !== this.description
    },
    options () {
      if (!this.mayEdit) return []
      return [
        { icon: 'pen', textKey: 'region.public.desc.edit', callback: this.startEdit },
      ]
    },
  },
  methods: {
    startEdit () {
      this.editDescription = this.description
      this.editMode = true
    },
    async cancel () {
      if (this.isDescriptionChanged) {
        if (!await this.confirmationDialogue('region.public.desc.confirm_discard_changes', {
          okTitle: this.$t('region.public.discard_changes'),
          cancelTitle: this.$t('region.public.continue_editing'),
        })) return
      }
      this.editMode = false
    },
    async save () {
      if (this.isDescriptionChanged) {
        if (!await this.confirmationDialogue('region.public.desc.confirm_save_changes', {
          okTitle: this.$t('button.save'),
          okVariant: 'success',
        })) return
        this.loading = true
        try {
          await setPublicRegionData(this.regionId, { description: this.editDescription })
          await regionStore.fetchPublicRegionData(this.regionId, true)
          this.$emit('update:description', this.editDescription)
          this.editMode = false
        } catch (e) {
          pulseError(this.$t('error_unexpected'))
        } finally {
          this.loading = false
        }
        return
      }
      this.editMode = false
    },
  },
}
</script>

<template>
  <b-modal
    ref="modal"
    :title="$t(`resource_mosaic.${currentResourceId ? 'edit' : 'add'}`)"
    centered
    size="lg"
    no-close-on-esc
    no-close-on-backdrop
    :ok-title="$t('button.save')"
    :cancel-title="$t('button.cancel')"
    :ok-disabled="!name.length"
    @ok="okHandler"
  >
    <b-form>
      <b-form-group :label="$t('resource_mosaic.editModal.name.label')">
        <b-form-input
          v-model="name"
          :placeholder="$t('resource_mosaic.editModal.name.placeholder')"
          required
          trim
          maxlength="35"
        />
        <small v-if="name.length === 35" v-text="$t('resource_mosaic.editModal.name.max_length')" />
      </b-form-group>

      <b-form-group :label="$t('resource_mosaic.editModal.description.label')">
        <MarkdownInput
          ref="mdInput"
          conceal-toolbar
          variant="outline-primary"
          :placeholder="$t('resource_mosaic.editModal.description.placeholder')"
          :rows="2"
          allow-image-attachments
          :value.sync="description"
        />
      </b-form-group>

      <b-form-group :label="$t('resource_mosaic.editModal.categories.label')">
        <Multiselect
          v-model="categories"
          class="category-select"
          :multiple="true"
          :options="resourceCategories"
          :searchable="true"
          :close-on-select="false"
          track-by="id"
          label="name"
          :placeholder="$t('resource_mosaic.editModal.categories.placeholder')"
          :show-labels="false"
        />
      </b-form-group>

      <b-form-group :label="$t('resource_mosaic.editModal.openness')">
        <VueSlider
          v-model="openness"
          class="openness-slider"
          :min="1"
          :max="5"
          drag-on-click
          marks
          hide-label
          tooltip="none"
          :height="6"
        />
        <b-row class="slider-marks">
          <b-col cols="4">
            <small v-text="$t('resource_mosaic.openness.level_1')" />
          </b-col>
          <b-col cols="4" class="text-center">
            <small v-text="$t('resource_mosaic.openness.level_3')" />
          </b-col>
          <b-col cols="4" class="text-right">
            <small v-text="$t('resource_mosaic.openness.level_5')" />
          </b-col>
        </b-row>
      </b-form-group>

      <b-form-group>
        <b-form-checkbox v-model="isPrivate" switch>
          {{ $t('resource_mosaic.editModal.is_private') }}
        </b-form-checkbox>
      </b-form-group>
    </b-form>
  </b-modal>
</template>
<script setup>
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import Multiselect from 'vue-multiselect'
import VueSlider from 'vue-slider-component'
import { ref, defineExpose, defineEmits, computed, nextTick } from 'vue'
import { useResourceStore } from '@/stores/resources'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user'

const resourceStore = useResourceStore()
const userStore = useUserStore()
const { confirmationDialogue } = useConfirmationDialogue()

const name = ref('')
const description = ref('')
const categories = ref([])
const isPrivate = ref(false)
const openness = ref(3)
const modal = ref(null)
const mdInput = ref(null)
const currentResourceId = ref(null)

const resourceCategories = computed(() => resourceStore.categories)

const emit = defineEmits(['add', 'edit'])

async function okHandler (event) {
  event.preventDefault()
  if (description.value.length < 50 && !currentResourceId.value) {
    if (!await confirmationDialogue(`resource_mosaic.editModal.description_warning.${description.value ? 'short' : 'none'}`, {
      title: i18n('resource_mosaic.editModal.description_warning.title'),
      okTitle: i18n('button.yes_save'),
      okVariant: 'primary',
      cancelTitle: i18n('button.prev'),
    })) return
  }
  const images = await mdInput.value.uploadImages()
  const resource = {
    name: name.value,
    description: description.value,
    categories: categories.value.map(category => category.id),
    isPrivate: !!isPrivate.value,
    openness: openness.value,
    images,
  }

  emit(currentResourceId.value ? 'edit' : 'add', resource)
  modal.value.hide()
}

defineExpose({
  async showNew () {
    const userHasResources = resourceStore.getResourcesByUser(userStore.getUserId).length > 0
    if (!userHasResources && !await confirmationDialogue('resource_mosaic.privacy_notice.text', {
      title: i18n('resource_mosaic.privacy_notice.title'),
      okTitle: i18n('resource_mosaic.privacy_notice.ok'),
      okVariant: 'primary',
    })) return
    currentResourceId.value = null
    name.value = ''
    description.value = ''
    categories.value = []
    isPrivate.value = false
    openness.value = 3
    modal.value.show()
    await nextTick()
    mdInput.value.setImages([])
  },
  async showEdit (resource) {
    currentResourceId.value = resource.id
    name.value = resource.name
    description.value = resource.description
    categories.value = resource.categories.map(id => resourceCategories.value.find(category => category.id === id))
    isPrivate.value = resource.isPrivate
    openness.value = resource.openness
    modal.value.show()
    await nextTick()
    mdInput.value.setImages(resource.images)
  },
})
</script>
<style lang="scss" scoped>
.openness-slider {
  padding-bottom: 25px !important;
  padding-top: 15px !important;
  margin-top: -10px;
}

.slider-marks {
  margin-top: -20px;
  pointer-events: none;
}
</style>

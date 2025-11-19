<template>
  <div v-if="props.selectedResource">
    <b-modal
      ref="modal"
      centered
      size="lg"
      ok-only
      @hidden="$emit('update:selectedResource', null)"
    >
      <template #modal-header="{ close }">
        <h5>
          {{ selectedResource.name }}
          <i
            v-if="props.selectedResource.isFavorite"
            v-b-tooltip="$t('resource_mosaic.favorite_tooltip')"
            class="fas fa-star"
            style="color: var(--fs-color-warning-500)"
          />
        </h5>
        <button
          v-if="previousResourcesStack.length > 1"
          type="button"
          class="close"
          @click="toPrevious"
        >
          &lt;
        </button>
        <button
          type="button"
          class="close ml-0"
          @click="close"
        >
          ×
        </button>
      </template>
      <div class="d-flex">
        <div class="mr-2">
          <Avatar
            :user="props.selectedResource.user"
            tooltip=""
            :size="100"
            class="d-block"
          />
          <b class="modal-unser-name" v-text="props.selectedResource.user.name" />
        </div>
        <div class="w-100">
          <Markdown
            v-if="props.selectedResource.description"
            :source="props.selectedResource.description"
            class="mb-3"
          />
          <div class="mb-2">
            <b-badge
              v-for="categoryId in props.selectedResource.categories"
              :key="categoryId"
              class="mr-2 mb-2"
              style="font-size: 1em"
              variant="info"
              v-text="categoriesMap[categoryId]"
            />
          </div>
          <p>
            <i class="fas fa-hand-holding-heart" />
            <b v-text="$t('resource_mosaic.openness.title') + ':'" />
            {{ props.selectedResource.openness }}/5
            (<i v-text="$t(`resource_mosaic.openness.level_${props.selectedResource.openness}`)" />)
          </p>
          <p v-if="props.selectedResource.isPrivate">
            <i class="fas fa-user-friends" />
            <i v-text="$t('resource_mosaic.is_private_hint')" />
          </p>
          <Gallery :images="props.selectedResource.images" :height-in-px="100" />
        </div>
      </div>
      <div v-if="newResources.length > 1">
        <hr>
        <h6 v-text="$t(`resource_mosaic.other_resources.new`)" />
        <ResourceTag
          v-for="resource in newResources"
          :key="resource.id"
          :resource="resource"
          @open="$emit('update:selectedResource', resource)"
        />
      </div>
      <div v-if="otherResourcesOfSameUser.length">
        <hr>
        <h6 v-text="$t(`resource_mosaic.other_resources.${isOwnSelected ? 'own' : 'other'}`, { user: props.selectedResource.user.name })" />
        <ResourceTag
          v-for="resource in otherResourcesOfSameUser"
          :key="resource.id"
          :resource="resource"
          @open="$emit('update:selectedResource', resource)"
        />
      </div>
      <div v-if="similarResources.length">
        <hr>
        <h6>
          {{ $t(`resource_mosaic.other_resources.similar`) }}
          <Info info-key="similar_resources" />
        </h6>
        <ResourceTag
          v-for="resource in similarResources"
          :key="resource.id"
          :resource="resource"
          @open="$emit('update:selectedResource', resource)"
        />
      </div>
      <template #modal-footer>
        <b-button
          v-if="isOwnSelected"
          variant="danger"
          @click="$emit('delete')"
        >
          <i class="fas fa-trash" /> {{ $t('button.delete') }}
        </b-button>
        <b-button
          v-if="isOwnSelected"
          variant="primary"
          @click="$emit('edit')"
        >
          <i class="fas fa-edit" /> {{ $t('button.edit') }}
        </b-button>
        <b-button
          @click="modal.hide()"
          v-text="$t('button.close')"
        />
        <b-button
          v-if="!isOwnSelected"
          :variant="props.selectedResource.isFavorite ? 'warning' : 'outline-warning'"
          @click="$emit('favorite', !props.selectedResource.isFavorite)"
        >
          <i class="fas fa-star" /> {{ $t('resource_mosaic.favorite') }}
        </b-button>
        <b-button
          v-if="!isOwnSelected"
          variant="primary"
          @click="$refs.messageModal.show()"
        >
          <i class="fas fa-comment" /> {{ $t('resource_mosaic.message') }}
        </b-button>
      </template>
    </b-modal>
    <b-modal
      ref="messageModal"
      :title="$t('resource_mosaic.message_modal.title', { name: props.selectedResource.name })"
      centered
      :ok-disabled="!message.length"
      :ok-title="$t('button.send')"
      :cancel-title="$t('button.cancel')"
      @ok="$emit('request', message)"
    >
      {{ $t('resource_mosaic.message_modal.intro', { owner: props.selectedResource.user.name }) }}
      <blockquote class="mb-3">
        {{ $t('resource_mosaic.message_modal.request_for') }} "<a href="#" v-text="props.selectedResource.name" />":
        <b-form-textarea
          v-model="message"
          :placeholder="$t('resource_mosaic.message_modal.placeholder')"
          max-rows="10"
          class="mt-2"
        />
      </blockquote>
      <b-alert show variant="success">
        <i class="fas fa-info-circle" />
        {{ $t('resource_mosaic.message_modal.polite_info') }}
      </b-alert>
    </b-modal>
  </div>
</template>
<script setup>
import ResourceTag from './ResourceTag.vue'
import { defineProps, computed, ref, watch, nextTick, defineEmits } from 'vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import Avatar from '@/components/Avatar/Avatar.vue'
import { useUserStore } from '@/stores/user.js'
import { setUrlParam } from '@/browser'
import { useResourceStore } from '@/stores/resources'
import Gallery from '@/components/Images/Gallery.vue'
import Info from '@/components/Help/Info.vue'

const userStore = useUserStore()
const resourceStore = useResourceStore()

const emit = defineEmits(['update:selectedResource'])

const props = defineProps({
  selectedResource: { type: Object, default: null },
  newSinceId: { type: Number, default: null },
})
const categoriesMap = computed(() => resourceStore.categoriesMap)

const modal = ref(null)
const message = ref('')
const previousResourcesStack = ref([])

watch(() => props.selectedResource, async () => {
  await nextTick()
  setUrlParam('resourceId', props.selectedResource?.id)
  if (!props.selectedResource) {
    previousResourcesStack.value = []
    return modal?.value?.hide?.()
  }
  modal.value.show()
  previousResourcesStack.value.push(props.selectedResource)
})

const isOwnSelected = computed(() => props.selectedResource?.user?.id === userStore.getUserId)

const similarResources = computed(() => {
  if (!props.selectedResource.categories.length) return []
  return resourceStore.getResourcesByCategories(props.selectedResource.categories)
    .filter(resource => resource.id !== props.selectedResource.id)
    .slice(0, 10)
})

const otherResourcesOfSameUser = computed(() => {
  return resourceStore.getResourcesByUser(props.selectedResource.user.id)
    .filter(resource => resource.id !== props.selectedResource.id)
})

const newResources = computed(() => {
  if (!props.newSinceId || props.selectedResource.id < props.newSinceId) return []
  return resourceStore.resources.filter(resource => resource.id >= props.newSinceId && resource.isHomeRegion)
})

function toPrevious () {
  previousResourcesStack.value.pop()

  // pop twice, because upon load the loaded resource gets re-added
  emit('update:selectedResource', previousResourcesStack.value.pop())
}

</script>
<style scoped>
.modal-unser-name {
  width: 0;
  min-width: 100%;
  text-align: center;
  display: inline-block;
}
</style>

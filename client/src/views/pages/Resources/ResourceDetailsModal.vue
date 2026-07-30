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
          <span ref="resourceName" v-text="selectedResource.name" />
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
        <div v-if="props.selectedResource.user" class="d-none d-md-block mr-2">
          <Avatar
            :user="props.selectedResource.user"
            tooltip=""
            :size="100"
            class="d-block"
          />
          <b
            ref="userNameDesktop"
            class="modal-user-name"
            v-text="props.selectedResource.user?.name"
          />
        </div>
        <div class="w-100">
          <div v-if="props.selectedResource.user" class="d-block d-md-none mb-2">
            <Avatar
              :user="props.selectedResource.user"
              tooltip=""
              :size="75"
            />
            <h6 class="d-inline ml-1">
              <a
                ref="userNameMobile"
                :href="$url('profile', props.selectedResource.user.id)"
                v-text="props.selectedResource.user?.name"
              />:
            </h6>
          </div>
          <Markdown
            v-if="props.selectedResource.description"
            ref="description"
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
            >
              {{ categoriesMap[categoryId] }}
            </b-badge>
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
          <p v-if="props.selectedResource.regionId && !props.selectedResource.user">
            <i class="fas fa-people-group" />
            <i v-text="$t('resource_mosaic.is_commons')" />
            <!-- TODO maybe add Info element? -->
          </p>
          <p v-if="props.selectedResource.regionId">
            <i class="fas fa-location-pin-lock" />
            <b>{{ $t('resource_mosaic.restricted_to') }} </b>
            <a :href="url('resources', region.id)">{{ region.name }}</a>
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
        <h6 v-text="$t(`resource_mosaic.other_resources.${sameUserTranslationKey}`, { user: props.selectedResource.user?.name })" />
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
          v-if="mayDeleteSelected"
          variant="danger"
          @click="removeResource()"
        >
          <i class="fas fa-trash" /> {{ $t('button.delete') }}
        </b-button>
        <b-button
          v-if="mayEditSelected"
          variant="primary"
          @click="$emit('edit')"
        >
          <i class="fas fa-edit" /> {{ $t('button.edit') }}
        </b-button>
        <b-button
          @click="modal.hide()"
        >
          {{ $t('button.close') }}
        </b-button>
        <b-button
          v-if="!isOwnSelected"
          :variant="props.selectedResource.isFavorite ? 'warning' : 'outline-warning'"
          @click="favorite()"
        >
          <i class="fas fa-star" /> {{ $t('resource_mosaic.favorite') }}
        </b-button>
        <b-button
          v-if="!isOwnSelected && props.selectedResource.user"
          variant="primary"
          @click="$refs.messageModal.show()"
        >
          <i class="fas fa-comment" /> {{ $t('resource_mosaic.message') }}
        </b-button>
      </template>
    </b-modal>
    <b-modal
      v-if="props.selectedResource.user"
      ref="messageModal"
      :title="$t('resource_mosaic.message_modal.title', { name: props.selectedResource.name })"
      centered
      :ok-disabled="!message.length"
      :ok-title="$t('button.send')"
      :cancel-title="$t('button.cancel')"
      @ok="sendRequest(message)"
    >
      {{ $t('resource_mosaic.message_modal.intro', { owner: props.selectedResource.user?.name }) }}
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
import { useUserStore } from '@/stores/user'
import { setUrlParam } from '@/browser'
import { useResourceStore } from '@/stores/resources'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import Gallery from '@/components/Images/Gallery.vue'
import Info from '@/components/Help/Info.vue'
import { getConversationIdForConversationWithUser, sendMessage } from '@/api/conversations'
import i18n from '@/helper/i18n'
import { pulseSuccess } from '@/script'
import { url } from '@/helper/urls'
import { useRegionStore } from '@/stores/regions'
import DataGroups from '@/stores/groups'

const userStore = useUserStore()
const resourceStore = useResourceStore()
const regionStore = useRegionStore()
const { confirmationDialogue } = useConfirmationDialogue()

const emit = defineEmits(['update:selectedResource'])

const props = defineProps({
  selectedResource: { type: Object, default: null },
  newSinceId: { type: Number, default: null },
  searchString: { type: String, default: '' },
  groupId: { type: Number, default: 0 }, // required for sending requests
})
const categoriesMap = computed(() => resourceStore.categoriesMap)

const modal = ref(null)
const description = ref(null)
const resourceName = ref(null)
const userNameDesktop = ref(null)
const userNameMobile = ref(null)
const message = ref('')
const previousResourcesStack = ref([])

watch(() => props.selectedResource, async () => {
  await nextTick()
  setUrlParam('resourceId', props.selectedResource?.id)
  if (!props.selectedResource) {
    previousResourcesStack.value = []
    return modal.value?.hide?.()
  }
  modal.value.show()
  previousResourcesStack.value.push(props.selectedResource)

  setSearchResultHighlight()
})

const isOwnSelected = computed(() => props.selectedResource?.user?.id === userStore.getUserId)

const mayEditSelected = computed(() =>
  isOwnSelected.value ||
  (
    resourceStore.permissions.mayEditCommonsResourcesInRegion &&
    props.selectedResource.regionId === props.groupId &&
    !props.selectedResource?.user
  ),
)

const mayDeleteSelected = computed(() =>
  userStore.isOrga || mayEditSelected.value,
)

const similarResources = computed(() => {
  if (!props.selectedResource.categories.length) return []
  return resourceStore.getResourcesByCategories(props.selectedResource.categories)
    .filter(resource => resource.id !== props.selectedResource.id)
    .slice(0, 10)
})

const otherResourcesOfSameUser = computed(() => {
  return resourceStore.getResourcesByUser(props.selectedResource.user?.id)
    .filter(resource => resource.id !== props.selectedResource.id)
})

const newResources = computed(() => {
  if (!props.newSinceId || props.selectedResource.id < props.newSinceId) return []
  return resourceStore.resources.filter(resource => resource.id >= props.newSinceId && resource.isHomeRegion)
})

const groups = computed(() => DataGroups.getters.get() ?? [])
const region = computed(() => {
  if (!props.selectedResource.regionId) return null
  return regionStore.findRegion(props.selectedResource.regionId) ?? groups.value.find(group => group.id === props.selectedResource.regionId) ?? null
})

const sameUserTranslationKey = computed(() => {
  if (!props.selectedResource?.user) {
    return 'commons'
  }
  return isOwnSelected.value ? 'own' : 'other'
})

function toPrevious () {
  previousResourcesStack.value.pop()

  // pop twice, because upon load the loaded resource gets re-added
  emit('update:selectedResource', previousResourcesStack.value.pop())
}

async function setSearchResultHighlight () {
  if (!CSS.highlights || !window.Highlight || !props.searchString) return
  CSS.highlights.clear()

  // wait for modal to be ready
  for (let i = 0; !description.value?.$el; i++) {
    await nextTick()
    if (i > 5) return
  }
  await nextTick()

  const elementsToSearch = [description.value.$el, resourceName.value, userNameDesktop.value, userNameMobile.value]
  const allTextNodes = []
  for (const element of elementsToSearch) {
    if (!element) continue
    const treeWalker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT)
    for (let node; (node = treeWalker.nextNode());) allTextNodes.push(node)
  }

  const queryWords = props.searchString.toLowerCase().split(/\s+/)
  if (!queryWords.length) return

  const wordPattern = new RegExp(queryWords.map(w =>
    w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
  ).join('|'), 'gi')

  const ranges = []
  for (const el of allTextNodes) {
    const text = el.textContent
    for (const match of text.matchAll(wordPattern)) {
      const range = new Range()
      range.setStart(el, match.index)
      range.setEnd(el, match.index + match[0].length)
      ranges.push(range)
    }
  }

  const searchResultsHighlight = new window.Highlight(...ranges.flat())
  CSS.highlights.set('search-results', searchResultsHighlight)
}

async function removeResource () {
  if (!await confirmationDialogue('resource_mosaic.confirm_delete')) return
  await resourceStore.removeResource(props.selectedResource.id)
  emit('update:selectedResource', null)
}

function favorite () {
  resourceStore.favoriteResource(props.selectedResource.id, !props.selectedResource.isFavorite)
}

async function sendRequest (message) {
  const conversationId = (await getConversationIdForConversationWithUser(props.selectedResource.user.id)).id
  let header = i18n('resource_mosaic.message_modal.request_for')
  header = `> ${header} "[${props.selectedResource.name}](${url('resource', props.groupId, props.selectedResource.id)})"\n\n`
  await sendMessage(conversationId, header + message)
  pulseSuccess(i18n('resource_mosaic.message_modal.request_sent'))
}
</script>
<style scoped>
.modal-user-name {
  width: 0;
  min-width: 100%;
  text-align: center;
  display: inline-block;
}
</style>
<style>
::highlight(search-results) {
  background-color: var(--fs-color-info-alpha-30);
}
</style>

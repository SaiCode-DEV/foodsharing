<template>
  <div>
    <Container
      :title="$i18n('resource_mosaic.my_resources')"
      info-key="my_resources"
    >
      <div v-if="!resources" class="list-group-item text-center">
        <span
          v-for="(width, i) in [8, 12, 10, 15]"
          :key="i"
          class="d-inline-block mx-2"
        >
          <b-skeleton
            :width="`${width}em`"
            height="25px"
            class="d-inline-block"
            style="border-radius: 25px;"
          />
        </span>
      </div>
      <div v-else class="list-group-item">
        <div class=" text-center">
          <ResourceTag
            v-for="resource in myResources"
            :key="resource.id"
            :resource="resource"
            @open="selectedResource = resource"
          />
        </div>
        <p v-if="!myResources?.length" v-text="$i18n('resource_mosaic.no_own_resources')" />
      </div>
      <div
        v-if="hasMaxResources"
        class="list-group-item text-center"
        v-text="$i18n('resource_mosaic.max_resources', { max: MAX_OWN_RESOURCES })"
      />
      <ContainerButton
        v-if="resources && !hasMaxResources"
        variant="success"
        text-key="resource_mosaic.add"
        icon="fas fa-plus"
        @click="editResourceModal?.showNew()"
      />
    </Container>
    <Container
      :title="$i18n('resource_mosaic.title')"
      info-key="resource_mosaic"
    >
      <div v-if="resources" class="list-group-item">
        <div class="filter-section mb-2">
          <div class="row">
            <div class="col-md-8 order-md-1 mb-2">
              <Multiselect
                v-model="selectedCategories"
                class="category-select"
                :multiple="true"
                :options="resourceCategories"
                :searchable="true"
                :close-on-select="true"
                track-by="id"
                label="name"
                :placeholder="$i18n('resource_mosaic.categories_filter_placeholder')"
                :show-labels="false"
              />
            </div>
            <div class="col-md-8 order-md-3 mb-2">
              <b-input
                v-model="searchString"
                :placeholder="$i18n('resource_mosaic.search_placeholder')"
              />
            </div>
            <div class="col-md-4 align-content-center order-md-2">
              <b-form-checkbox v-model="includeActiveUsersOnly" switch>
                {{ $i18n('resource_mosaic.filter.active') }}
              </b-form-checkbox>
            </div>
            <div class="col-md-4 align-content-center order-md-4">
              <b-form-checkbox v-model="includeFavoritesOnly" switch>
                {{ $i18n('resource_mosaic.filter.favorites') }}
              </b-form-checkbox>
            </div>
            <div
              v-if="isAccessibleRegion"
              class="col-md-4 align-content-center order-md-6"
            >
              <b-form-checkbox v-model="includeHomeRegionUsersOnly" switch>
                {{ $i18n('resource_mosaic.filter.home_region') }}
              </b-form-checkbox>
            </div>
            <div class="col-md-8 order-md-5 mb-md-0 mb-2 mt-2 mt-md-0">
              <b-button
                size="sm"
                block
                variant="primary"
                @click="nextSorting"
              >
                <i :class="`mr-1 fas fa-${sortings[sorting].icon}`" />
                {{ $i18n(`resource_mosaic.order.${sortings[sorting].translationKey}`) }}
              </b-button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!resources" class="list-group-item text-center">
        <span
          v-for="(width, i) in [11, 17, 8, 13, 16, 15]"
          :key="i"
          class="d-inline-block mx-2"
        >
          <b-skeleton
            :width="`${width}em`"
            height="25px"
            class="d-inline-block"
            style="border-radius: 25px;"
          />
        </span>
      </div>
      <div v-else class="list-group-item">
        <div class="text-center">
          <p class="font-weight-bold" v-text="$i18n('resource_mosaic.list_status.' + listStatus, { region: props.groupName, count: filtered.length })" />
          <PaginatedContent :items="filtered" :page-size="50">
            <template #default="{ currentPageItems }">
              <ResourceTag
                v-for="resource in currentPageItems"
                :key="resource.id"
                :resource="resource"
                @open="selectedResource = resource"
              />
            </template>
          </PaginatedContent>
        </div>
      </div>
    </Container>
    <ResourceDetailsModal
      v-if="resources"
      ref="resourceDetailsModal"
      :selected-resource.sync="selectedResource"
      :new-since-id="newSinceId"
      @delete="removeResource"
      @edit="editResourceModal.showEdit(selectedResource)"
      @request="sendRequest"
      @favorite="favorite"
    />
    <EditResourceModal
      v-if="resources"
      ref="editResourceModal"
      @add="addResource"
      @edit="editResource"
    />
  </div>
</template>
<script setup>
import Container from '@/components/Container/Container.vue'
import ResourceTag from './ResourceTag.vue'
import { defineProps, onMounted, ref, computed, nextTick } from 'vue'
import { pulseSuccess, pulseWarning } from '@/script'
import Multiselect from 'vue-multiselect'
import { useUserStore } from '@/stores/user.js'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import ResourceDetailsModal from './ResourceDetailsModal.vue'
import EditResourceModal from './EditResourceModal.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { getConversationIdForConversationWithUser, sendMessage } from '@/api/conversations'
import { url } from '@/helper/urls'
import i18n from '@/helper/i18n'
import PaginatedContent from '@/components/Container/PaginatedContent.vue'
import { GET } from '@/browser'
import { useResourceStore } from '@/stores/resources'
import { useRegionStore } from '@/stores/regions'

const { confirmationDialogue } = useConfirmationDialogue()
const userStore = useUserStore()
const resourceStore = useResourceStore()
const regionStore = useRegionStore()

const props = defineProps({
  groupName: { type: String, required: true },
  groupId: { type: Number, required: true },
})

const MAX_OWN_RESOURCES = ref(10)
const selectedCategories = ref([])
const searchString = ref('')
const includeActiveUsersOnly = ref(false)
const includeHomeRegionUsersOnly = ref(false)
const includeFavoritesOnly = ref(false)
const selectedResource = ref(null)
const resourceDetailsModal = ref(null)
const editResourceModal = ref(null)
const newSinceId = ref(null)
const sorting = ref(0)
const sortings = [
  {
    translationKey: 'shuffle',
    icon: 'shuffle',
    callback: resourceStore.shuffleResources,
  },
  {
    translationKey: 'sort_openness',
    icon: 'arrow-down-wide-short',
    callback: resourceStore.sortResources.bind(null, 'openness'),
  },
  {
    translationKey: 'sort_age',
    icon: 'arrow-down-wide-short',
    callback: resourceStore.sortResources,
  },
]

function nextSorting () {
  sorting.value = (sorting.value + 1) % sortings.length
  sortings[sorting.value].callback()
}

onMounted(async () => {
  await resourceStore.fetchResourceCategories()
  await resourceStore.fetchResources(props.groupId)
  resourceStore.shuffleResources()

  await nextTick()
  newSinceId.value = +GET('newSinceId')
  const openResourceId = +GET('resourceId')
  if (!openResourceId) return
  const openResource = resources.value.find(resource => resource.id === openResourceId)
  if (openResource) selectedResource.value = openResource
  else pulseWarning(i18n('resource_mosaic.resource_not_found_warning'))
})

const resources = computed(() => resourceStore.resources)
const resourceCategories = computed(() => resourceStore.categories)

const myResources = computed(() => resourceStore.getResourcesByUser(userStore.getUserId))
const hasMaxResources = computed(() => myResources?.value?.length >= MAX_OWN_RESOURCES.value)

const filtered = computed(() => {
  if (!resources.value) return null
  let selected = resourceStore.getResourcesByCategories(selectedCategories.value.map(category => category.id))
  if (includeFavoritesOnly.value) {
    selected = selected.filter(resource => resource.isFavorite)
  }
  if (includeHomeRegionUsersOnly.value) {
    selected = selected.filter(resource => resource.isHomeRegion)
  }
  if (includeActiveUsersOnly.value) {
    selected = selected.filter(resource => resource.isUserActive)
  }
  if (searchString.value) {
    const words = searchString.value.toLowerCase().split(/\s+/)
    selected = selected.filter(resource => words.every(
      word => +word === resource.user.id || resource.name.toLowerCase().includes(word) || resource.user.name.toLowerCase().includes(word),
    ))
  }
  return selected
})

const listStatus = computed(() => {
  if (!resources.value.length) return 'none_in_region'
  if (!filtered.value.length) return 'none_in_filter'
  if (resources.value.length === filtered.value.length) return 'total'
  return 'filter'
})

const isAccessibleRegion = computed(() => {
  return regionStore.accessibleRegions.find(region => region.id === props.groupId)
})

async function addResource (resource) {
  await resourceStore.addResource(resource)
}
async function editResource (resource) {
  selectedResource.value = await resourceStore.editResource(selectedResource.value.id, resource)
}
async function removeResource () {
  if (!await confirmationDialogue('resource_mosaic.confirm_delete')) return
  await resourceStore.removeResource(selectedResource.value.id)
  selectedResource.value = null
}
function favorite (newIsFavorite) {
  resourceStore.favoriteResource(selectedResource.value.id, newIsFavorite)
}

async function sendRequest (message) {
  const conversationId = (await getConversationIdForConversationWithUser(selectedResource.value.user.id)).id
  let header = i18n('resource_mosaic.message_modal.request_for')
  header = `> ${header} "[${selectedResource.value.name}](${url('resource', props.groupId, selectedResource.value.id)})"\n\n`
  await sendMessage(conversationId, header + message)
  pulseSuccess(i18n('resource_mosaic.message_modal.request_sent'))
}
</script>
<style>
/* Adjust multiselect design to input */
.category-select .multiselect__tags { border-color: #ced4da; }
.category-select .multiselect__placeholder { color: #6c757d; }
.category-select .multiselect__input { border: none; }
.category-select .multiselect__input:focus { box-shadow: none !important; }

/* Make multiselect tags blue */
.category-select .multiselect__tag {
  background: var(--fs-color-info-500);
  font-weight: bold;
}
.category-select .multiselect__tag-icon::after { color: white; }
</style>

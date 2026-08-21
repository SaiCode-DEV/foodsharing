<template>
  <div>
    <Container
      :title="$t('resource_mosaic.my_resources_in', { groupName })"
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
      <div v-else class="list-group-item text-center">
        <ResourceTag
          v-for="resource in myResources"
          :key="resource.id"
          :resource="resource"
          @open="selectedResource = resource"
        />
        <p v-if="!myResources?.length" v-text="$t('resource_mosaic.no_own_resources')" />
      </div>
      <div
        v-if="hasMaxResources || resourceStore.otherOwnResourceCount"
        class="list-group-item text-center"
      >
        <div v-if="hasMaxResources" v-text="$t('resource_mosaic.max_resources', { max: MAX_OWN_RESOURCES })" />
        <div v-if="resourceStore.otherOwnResourceCount" v-text="$t('resource_mosaic.has_resources_elsewhere', { count: resourceStore.otherOwnResourceCount, groupName: props.groupName })" />
      </div>
      <ContainerButton
        v-if="resources && !hasMaxResources"
        variant="success"
        text-key="resource_mosaic.add"
        icon="fas fa-plus"
        @click="editResourceModal?.showNew(false)"
      />
    </Container>
    <Container
      v-if="resources && resourceStore.permissions.mayEditCommonsResourcesInRegion"
      :title="$t('resource_mosaic.commons_resources_in', { groupName })"
      info-key="commons_resources"
    >
      <div class="list-group-item text-center">
        <ResourceTag
          v-for="resource in commonsResources"
          :key="resource.id"
          :resource="resource"
          @open="selectedResource = resource"
        />
        <p v-if="!commonsResources?.length" v-text="$t('resource_mosaic.no_commons_resources', { groupName })" />
      </div>
      <ContainerButton
        variant="success"
        text-key="resource_mosaic.add_commons"
        icon="fas fa-people-group"
        @click="editResourceModal?.showNew(true)"
      />
    </Container>
    <Container
      :title="$t('resource_mosaic.title')"
      info-key="resource_mosaic"
    >
      <div v-if="resources" class="list-group-item">
        <div class="filter-section mb-2">
          <div class="row">
            <div class="col-md-8 order-md-1 mb-2">
              <b-input
                v-model="searchString"
                :placeholder="$t('resource_mosaic.search_placeholder')"
              />
            </div>
            <div class="col-md-8 order-md-3 mb-2">
              <Multiselect
                v-model="selectedCategories"
                class="category-select"
                :multiple="true"
                :options="resourceCategories"
                :searchable="true"
                :close-on-select="true"
                track-by="id"
                label="name"
                :placeholder="$t('resource_mosaic.categories_filter_placeholder')"
                :show-labels="false"
              />
            </div>
            <div class="col-md-4 align-content-center order-md-2">
              <b-form-checkbox v-model="includeActiveUsersOnly" switch>
                {{ $t('resource_mosaic.filter.active') }}
              </b-form-checkbox>
            </div>
            <div class="col-md-4 align-content-center order-md-4">
              <b-form-checkbox v-model="includeFavoritesOnly" switch>
                {{ $t('resource_mosaic.filter.favorites') }}
              </b-form-checkbox>
            </div>
            <div
              v-if="isAccessibleRegion"
              class="col-md-4 align-content-center order-md-6"
            >
              <b-form-checkbox v-model="includeHomeRegionUsersOnly" switch>
                {{ $t('resource_mosaic.filter.home_region') }}
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
                {{ $t(`resource_mosaic.order.${sortings[sorting].translationKey}`) }}
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
          <p class="font-weight-bold" v-text="$t('resource_mosaic.list_status.' + listStatus, { region: props.groupName, count: filtered.length })" />
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
      :search-string="searchString"
      :group-id="props.groupId"
      @edit="editResourceModal.showEdit(selectedResource)"
    />
    <EditResourceModal
      v-if="resources"
      ref="editResourceModal"
      :current-region-id="groupId"
      @update:selected-resource="selectedResource = $event"
    />
  </div>
</template>
<script setup>
import Container from '@/components/Container/Container.vue'
import ResourceTag from './ResourceTag.vue'
import { defineProps, onMounted, ref, computed, nextTick } from 'vue'
import { pulseWarning } from '@/script'
import Multiselect from 'vue-multiselect'
import { useUserStore } from '@/stores/user'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import ResourceDetailsModal from './ResourceDetailsModal.vue'
import EditResourceModal from './EditResourceModal.vue'
import i18n from '@/helper/i18n'
import PaginatedContent from '@/components/Container/PaginatedContent.vue'
import { GET } from '@/browser'
import { useResourceStore } from '@/stores/resources'
import { useRegionStore } from '@/stores/regions'

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
const includeHomeRegionUsersOnly = ref(true)
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

// runs during setup, before the first render, so the resources of the region opened
// before never reach the template
resourceStore.resetRegionBoundState()

onMounted(async () => {
  await Promise.all([
    resourceStore.fetchResourceCategories(),
    resourceStore.fetchResourcePermissions(props.groupId),
  ])
  await resourceStore.fetchResources(props.groupId)
  resourceStore.fetchOtherOwnResourceCount(props.groupId)
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
const commonsResources = computed(() => resources.value.filter(resource => !resource.user && resource.regionId === props.groupId))
const hasMaxResources = computed(() => myResources?.value?.length + resourceStore.otherOwnResourceCount >= MAX_OWN_RESOURCES.value)

const filtered = computed(() => {
  if (!resources.value) return null
  let selected = resourceStore.getResourcesByCategories(selectedCategories.value.map(category => category.id))
  if (includeFavoritesOnly.value) {
    selected = selected.filter(resource => resource.isFavorite)
  }
  if (isAccessibleRegion.value && includeHomeRegionUsersOnly.value) {
    selected = selected.filter(resource => resource.isHomeRegion)
  }
  if (includeActiveUsersOnly.value) {
    selected = selected.filter(resource => resource.isUserActive)
  }
  if (searchString.value) {
    const words = searchString.value.toLowerCase().split(/\s+/)
    selected = selected.filter(resource => {
      const description = resource.description?.toLowerCase() || ''
      return words.every(
        word => +word === resource.user?.id || resource.name.toLowerCase().includes(word) || resource.user?.name?.toLowerCase?.()?.includes?.(word) || description.includes(word),
      )
    })
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
</script>

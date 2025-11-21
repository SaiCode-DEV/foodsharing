<template>
  <div class="mt-2">
    <Info info-key="my_resources" class="float-right" />
    <div v-if="!resources" class="text-center">
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
    <div v-else class="text-center">
      <ResourceTag
        v-for="resource in resources"
        :key="resource.id"
        :resource="resource"
        @open="selectedResource = resource"
      />
      <p v-if="!resources?.length" v-text="$t('resource_mosaic.no_own_resources')" />
      <b-button
        v-if="resources && !hasMaxResources"
        variant="primary"
        class="add-resource-btn"
        size="sm"
        @click="editResourceModal?.showNew()"
      >
        <i class="fas fa-plus" />
        {{ $t('resource_mosaic.add') }}
      </b-button>
      <p v-if="hasMaxResources" class="mt-2">
        {{ $t('resource_mosaic.max_resources', { max: MAX_OWN_RESOURCES }) }}
      </p>
    </div>
    <ResourceDetailsModal
      v-if="resources"
      ref="resourceDetailsModal"
      :selected-resource.sync="selectedResource"
      @edit="editResourceModal.showEdit(selectedResource)"
    />
    <EditResourceModal
      v-if="resources"
      ref="editResourceModal"
      @update:selected-resource="selectedResource = $event"
    />
  </div>
</template>
<script setup>
import { useResourceStore } from '@/stores/resources'
import { computed, onMounted, ref } from 'vue'
import ResourceTag from './ResourceTag.vue'
import ResourceDetailsModal from './ResourceDetailsModal.vue'
import EditResourceModal from './EditResourceModal.vue'
import Info from '@/components/Help/Info.vue'

const resourceStore = useResourceStore()

const MAX_OWN_RESOURCES = ref(10) // TODO: base of of central const
const selectedResource = ref(null)
const resourceDetailsModal = ref(null)
const editResourceModal = ref(null)

onMounted(async () => {
  await resourceStore.fetchResourceCategories()
  await resourceStore.fetchOwnResources()
})

const resources = computed(() => resourceStore.resources)
const hasMaxResources = computed(() => resources?.value?.length >= MAX_OWN_RESOURCES.value)

</script>
<style>
.add-resource-btn {
  height: 1.8em;
  border-radius: 1em;
  line-height: 1em;
}
</style>

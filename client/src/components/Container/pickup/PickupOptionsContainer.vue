<template>
  <Container
    tag="pickup-options"
    :title="$i18n('pickup.overview.tab.options.name')"
    class="pickup-options"
  >
    <PickupFieldSkeleton v-if="loading" />
    <PaginatedContent
      v-else
      :items="filteredOptions"
      :page-size="pageSize"
      @update:current-page-items="items => { displayedOptions = items }"
    >
      <template v-if="!useCondensedDesign">
        <PickupField
          v-for="(entry, key) in displayedOptions"
          :key="key"
          :entry="entry"
          mute-sign-ups
        />
      </template>
      <template v-else>
        <OptionsByDayField
          v-for="dayOptions in displayedOptionsByDay"
          :key="dayOptions[0].date"
          :options="dayOptions"
        />
      </template>
      <div v-if="!filteredOptions.length" class="list-group-item">
        <span v-text="$i18n('pickup.overview.tab.options.empty')" />
      </div>
      <div v-if="fetchedTime" class="list-group-item py-1">
        <small v-text="$i18n('globals.updated')" />
        <Time :time="fetchedTime" class="float-right" />
      </div>
    </PaginatedContent>
    <template #options>
      <Info info-key="pickupOptions" />
      <OverflowMenu :options="menuOptions">
        <template #added-content>
          <b-dropdown-form>
            <b-form-checkbox v-model="showRegistered" switch>
              {{ $i18n('pickup.overview.menu.registeredSwitch') }}
            </b-form-checkbox>
            <b-form-checkbox
              v-if="isActiveStoreManager"
              v-model="showManagedStoresOnly"
              switch
            >
              {{ $i18n('pickup.overview.menu.managedOnlySwitch') }}
            </b-form-checkbox>
            <b-form-checkbox v-model="useCondensedDesign" switch>
              {{ $i18n('pickup.overview.menu.useCondensedDesign') }}
            </b-form-checkbox>
          </b-dropdown-form>
        </template>
      </OverflowMenu>
    </template>
  </Container>
</template>
<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { usePickupStore } from '@/stores/pickups'
import Container from '../Container.vue'
import PickupField from './PickupField.vue'
import Time from '@/components/Time.vue'
import PickupFieldSkeleton from '@/components/Skeleton/PickupField.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import DataStores from '@/stores/stores'
import Info from '@/components/Help/Info.vue'
import OptionsByDayField from './OptionsByDayField.vue'
import PaginatedContent from '../PaginatedContent.vue'

const REFRESH_WAIT_TIME = 60000
const AUTO_REFRESH_TIME = 1200000 // 20 minutes
const LOCAL_STORAGE_KEY = {
  showRegistered: 'show-registered-signups-in-pickup-options',
  showManagedStoresOnly: 'show-managed-stores-only-in-pickup-options',
  useCondensedDesign: 'use-condensed-design-in-pickup-options',
}

const pickupStore = usePickupStore()
const fetchedTime = ref(null)
const loading = ref(true)
const mayRefresh = ref(false)
const showRegistered = ref(JSON.parse(localStorage.getItem(LOCAL_STORAGE_KEY.showRegistered)) ?? true)
const showManagedStoresOnly = ref(JSON.parse(localStorage.getItem(LOCAL_STORAGE_KEY.showManagedStoresOnly)) ?? false)
const useCondensedDesign = ref(JSON.parse(localStorage.getItem(LOCAL_STORAGE_KEY.useCondensedDesign)) ?? false)
const displayedOptions = ref([])
const now = ref(Date.now())

const managedStores = computed(() => new Set(DataStores.getters.getManaging().map(store => store.id)))
const isActiveStoreManager = computed(() => managedStores.value.size > 0)

const filteredOptions = computed(() => {
  let list = pickupStore.getOptions || []
  list = list.filter(x => (new Date(x.date)).getTime() > now.value)
  if (!showRegistered.value) {
    list = list.filter(x => x.isConfirmed === null)
  }
  if (isActiveStoreManager.value && showManagedStoresOnly.value) {
    list = list.filter(x => managedStores.value.has(x.store.id))
  }
  return list
})

const displayedOptionsByDay = computed(() => {
  const groups = []
  let group, currentDay
  for (const option of displayedOptions.value) {
    const day = (new Date(option.date)).toDateString()
    if (day !== currentDay) {
      group = []
      groups.push(group)
      currentDay = day
    }
    group.push(option)
  }
  return groups
})

const pageSize = computed(() => useCondensedDesign.value ? 10 : 5)

const menuOptions = computed(() => [
  { hide: !mayRefresh.value, textKey: 'menu.entry.refresh', icon: 'refresh', callback: refresh },
])

async function refresh (force = true) {
  let age = 0
  if (!await pickupStore.fetchOptions(force)) { // if the cache was used
    age = await pickupStore.getOptionsCacheAge()
  }
  scheduleRemovalOfOutdatedOptions()
  updateMayRefresh(age)
  restartAutoRefresh() // (Re)start auto-refresh
}

async function updateMayRefresh (age) {
  mayRefresh.value = age > REFRESH_WAIT_TIME
  fetchedTime.value = new Date(Date.now() - age)
  if (age < REFRESH_WAIT_TIME) {
    await new Promise(resolve => setTimeout(resolve, REFRESH_WAIT_TIME - age))
    mayRefresh.value = true
  }
}

let timeoutId = null
function scheduleRemovalOfOutdatedOptions () {
  window.clearTimeout(timeoutId)
  if (!pickupStore.getOptions.length) return
  const nextOptionTime = new Date(pickupStore.getOptions[0].date)
  const timeout = nextOptionTime.getTime() - Date.now() + 1000 // one second buffer to make sure that timing imperfections don't cause problems
  timeoutId = window.setTimeout(function () {
    now.value = Date.now() // leads to recalculating filteredOptions
    scheduleRemovalOfOutdatedOptions()
  }, timeout)
}

let autoRefreshInterval = null

function restartAutoRefresh () {
  stopAutoRefresh() // Clear any existing interval
  autoRefreshInterval = setInterval(async () => {
    await refresh()
  }, AUTO_REFRESH_TIME)
}

function stopAutoRefresh () {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval)
    autoRefreshInterval = null
  }
}

watch(showRegistered, val => localStorage.setItem(LOCAL_STORAGE_KEY.showRegistered, val))
watch(showManagedStoresOnly, val => localStorage.setItem(LOCAL_STORAGE_KEY.showManagedStoresOnly, val))
watch(useCondensedDesign, val => localStorage.setItem(LOCAL_STORAGE_KEY.useCondensedDesign, val))

onMounted(async () => {
  await refresh(false)
  loading.value = false
})

onUnmounted(() => {
  stopAutoRefresh()
})

</script>

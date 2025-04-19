<template>
  <Container
    tag="pickup-options"
    :title="$i18n('pickup.overview.tab.options.name')"
    :toggle-visiblity="pickupStore.getOptions.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <PickupFieldSkeleton v-if="loading" />
    <template v-else>
      <PickupField
        v-for="(entry, key) in filteredList"
        :key="key"
        :entry="entry"
        mute-sign-ups
      />

      <div v-if="!filteredList.length" class="list-group-item">
        <span v-text="$i18n('pickup.overview.tab.options.empty')" />
      </div>
      <div v-if="fetchedTime" class="list-group-item py-1">
        <small v-text="$i18n('globals.updated')" />
        <Time :time="fetchedTime" class="float-right" />
      </div>
    </template>
    <template #options>
      <OverflowMenu :options="options">
        <template #added-content>
          <b-dropdown-form>
            <b-form-checkbox v-model="showRegistered" switch>
              {{ $i18n('pickup.overview.menu.registeredSwitch') }}
            </b-form-checkbox>
          </b-dropdown-form>
        </template>
      </OverflowMenu>
    </template>
  </Container>
</template>
<script>
import { usePickupStore } from '@/stores/pickups'
import Container from '../Container.vue'
import PickupField from './PickupField'
import ListToggleMixin from '@/mixins/ContainerToggleMixin'
import Time from '@/components/Time.vue'
import PickupFieldSkeleton from '@/components/Skeleton/PickupField.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
const REFRESH_WAIT_TIME = 60_000
const LOCAL_STORAGE_KEY = 'show-registered-signups-in-pickup-options'

export default {
  name: 'RegionList',
  components: { Container, PickupField, PickupFieldSkeleton, Time, OverflowMenu },
  mixins: [ListToggleMixin],
  setup () {
    return {
      pickupStore: usePickupStore(),
    }
  },
  data: () => ({
    data: [],
    fetchedTime: null,
    loading: true,
    mayRefresh: false,
    showRegistered: JSON.parse(localStorage.getItem(LOCAL_STORAGE_KEY)) ?? true,
  }),
  computed: {
    options () {
      return [
        { hide: !this.mayRefresh, textKey: 'menu.entry.refresh', icon: 'refresh', callback: this.refresh },
      ]
    },
  },
  watch: {
    showRegistered () {
      localStorage.setItem(LOCAL_STORAGE_KEY, this.showRegistered)
      this.setFilteredList()
    },
  },
  async mounted () {
    await this.pickupStore.fetchOptions()
    const age = await this.pickupStore.getOptionsCacheAge()
    this.setFilteredList()
    this.loading = false
    this.updateMayRefresh(age)
  },
  methods: {
    async refresh () {
      await this.pickupStore.fetchOptions(true)
      this.updateMayRefresh(0)
    },
    async updateMayRefresh (age) {
      this.mayRefresh = age > REFRESH_WAIT_TIME
      this.fetchedTime = new Date(Date.now() - age)
      if (age < REFRESH_WAIT_TIME) {
        await new Promise(resolve => window.setTimeout(resolve, REFRESH_WAIT_TIME - age))
        this.mayRefresh = true
      }
    },
    setFilteredList () {
      let list = this.pickupStore.getOptions
      if (!list.length) list = []
      if (!this.showRegistered) {
        list = list.filter(x => x.isConfirmed === null)
      }
      this.setList(list.slice(0, 50))
    },
  },
}
</script>

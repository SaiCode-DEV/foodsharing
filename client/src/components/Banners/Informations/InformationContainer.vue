<template>
  <div
    v-if="unseenEntries.length > 0"
    class="informations-wrapper position-relative"
    :class="{ 'multiple': unseenEntries.length > 1 }"
  >
    <InformationField
      :entry="unseenEntries[visibleIndex]"
      :active-index="visibleIndex"
      :entry-count="unseenEntries.length"
      @next="changeVisibleIndexBy(+1)"
      @prev="changeVisibleIndexBy(-1)"
      @close="closeVisible"
    />
  </div>
</template>

<script>
// Stores
import { useUserStore } from '@/stores/user'
// Components
import InformationField from './InformationField.vue'
// Mixin
import RouteAndDeviceCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'

export default {
  components: {
    InformationField,
  },
  mixins: [RouteAndDeviceCheckMixin],
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  data () {
    return {
      unseenEntries: [],
      visibleIndex: 0,
    }
  },
  async mounted () {
    // Fetch user details for the check whether the user has a calendar token
    await this.userStore.fetchDetails()

    const entries = this.createInformationEntries()
    this.unseenEntries = entries.filter(entry => !this.isSeen(entry))
  },
  methods: {
    getStorageKey (entry) {
      return `information_seen_${entry.field}`
    },
    isSeen (entry) {
      return JSON.parse(localStorage.getItem(this.getStorageKey(entry))) || false
    },
    setSeen (entry) {
      localStorage.setItem(this.getStorageKey(entry), JSON.stringify(true))
    },
    changeVisibleIndexBy (delta) {
      this.visibleIndex = this.visibleIndex + delta
      if (this.visibleIndex < 0) {
        this.visibleIndex = this.unseenEntries.length - 1
      } else if (this.visibleIndex > this.unseenEntries.length - 1) {
        this.visibleIndex = 0
      }
    },
    closeVisible () {
      this.setSeen(this.unseenEntries[this.visibleIndex])
      this.unseenEntries.splice(this.visibleIndex, 1)
      this.changeVisibleIndexBy(0)
    },
    createInformationEntries () {
      const list = []

      if (this.userStore.isFoodsaver && !this.userStore.hasCalendarToken) {
        list.push({
          icon: 'fa-calendar-alt',
          field: 'calendar_sync',
          links: [{
            text: 'information.calendar_sync.link',
            urlShortHand: 'settingsCalendar',
          }],
        })
      }

      if (this.userStore.isFoodsaver && !this.isSafari) {
        list.push({
          icon: 'fa-info-circle',
          field: 'push',
          links: [{
            text: 'information.push.link',
            urlShortHand: 'settingsNotifications',
          }],
        })
      }

      return list
    },
  },
}
</script>

<style lang="scss" scoped>
$shift: 0.5rem;

.informations-wrapper {
  margin-bottom: 1rem;

  &.multiple {
    margin-bottom: 1rem + $shift;
    margin-right: $shift;
  }
}

.multiple::after {
  position: absolute;
  width: 100%;
  height: 100%;
  left: $shift;
  top: $shift;
  z-index: -1;
  background-color: var(--fs-color-info-200);
  border: 1px solid var(--fs-color-info-300);
  border-radius: var(--border-radius);
  opacity: 0.5;
  content: '';
}
</style>

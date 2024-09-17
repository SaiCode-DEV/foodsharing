<template>
  <div
    class="informations-wrapper position-relative"
    :class="{ 'multiply': list.length > 1 }"
  >
    <InformationField
      v-for="(entry, key) in list"
      :key="key"
      :entry="entry"
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
      list: [],
    }
  },
  async mounted () {
    /**
     * Checks if the foodsaver has already an calendar token
     */
    if (this.userStore.isFoodsaver && !this.userStore.hasCalendarToken) {
      this.list.push({
        icon: 'fa-calendar-alt',
        field: 'calendar_sync',
        links: [{
          text: 'information.calendar_sync.link',
          urlShortHand: 'settingsCalendar',
        }],
      })
    }

    /**
     * Checks if the user is a foodsaver and does not use an safari browser
     */
    if (this.userStore.isFoodsaver && !this.isSafari) {
      this.list.push({
        icon: 'fa-info-circle',
        field: 'push',
        links: [{
          text: 'information.push.link',
          urlShortHand: 'settingsNotifications',
        }],
      })
    }
  },
}
</script>

<style lang="scss" scoped>
.informations-wrapper:empty {
  display: none;
}

.single * {
  margin-bottom: 1rem;
}

.multiply {
  margin-bottom: 2rem;
}

.multiply *:nth-child(n+2) {
  display: none !important;
}

.multiply::after {
  background-color: currentColor;
  border-radius: var(--border-radius);
  content: '';
  height: 100%;
  left: 0;
  opacity: 0.15;
  position: absolute;
  top: 0;
  transform: translateY(14px) scale(0.95);
  width: 100%;
  z-index: -1;
}
</style>

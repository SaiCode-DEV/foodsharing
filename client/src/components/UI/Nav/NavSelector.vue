<template>
  <div class="navigation-selector">
    <b-button-group :vertical="vertical">
      <b-button
        variant="outline-primary"
        :disabled="invalidCoords"
        @click="handleMainButtonClick"
      >
        <template v-if="selectedApp === null">
          <i class="fas fa-directions" />
        </template>
        <template v-else>
          <img
            height="30em"
            :src="`/img/navigation/${APPS.find(app => app.name === selectedApp).icon}`"
            :alt="selectedApp"
          >
        </template>
      </b-button>
      <b-dropdown
        ref="dropdownRef"
        right
        variant="outline-primary"
        size="sm"
        :class="vertical ? 'extra-small' : ''"
        :disabled="invalidCoords"
      >
        <b-dropdown-item
          v-for="app in APPS"
          :key="app.name"
          @click="() => selectApp(app.name)"
        >
          <img
            height="30em"
            :src="`/img/navigation/${app.icon}`"
            class="mr-2"
          >
          {{ app.displayName }}
        </b-dropdown-item>
      </b-dropdown>
    </b-button-group>
  </div>
</template>

<script setup>
import i18n from '@/helper/i18n'
import { ref, onMounted, defineProps } from 'vue'

const props = defineProps({
  latitude: {
    type: Number,
    default: 0,
  },
  longitude: {
    type: Number,
    default: 0,
  },
  vertical: {
    type: Boolean,
    default: false,
  },
})

const invalidCoords = !props.latitude || !props.longitude || (props.latitude === 0 && props.longitude === 0)

const APPS = [
  {
    name: 'google',
    icon: 'Google Maps.svg',
    displayName: i18n('navi.google_maps'),
  },
  {
    name: 'waze',
    icon: 'Waze.svg',
    displayName: i18n('navi.waze'),
  },
  {
    name: 'apple',
    icon: 'Apple Maps.svg',
    displayName: i18n('navi.apple_maps'),
  },
  {
    name: 'osmand',
    icon: 'OsmAnd.svg',
    displayName: i18n('navi.osmand'),
  },
  {
    name: 'komoot',
    icon: 'Komoot.svg',
    displayName: i18n('navi.komoot'),
  },
  {
    name: 'here',
    icon: 'HERE WeGo.svg',
    displayName: i18n('navi.here'),
  },
]

const STORAGE_KEY = 'preferred_nav_app'
const selectedApp = ref(null)
const dropdownRef = ref(null)

const handleMainButtonClick = () => {
  const savedApp = localStorage.getItem(STORAGE_KEY)
  if (!savedApp) {
    dropdownRef.value?.show()
  } else {
    openNavigation(selectedApp.value)
  }
}

const selectApp = (app) => {
  selectedApp.value = app
  localStorage.setItem(STORAGE_KEY, app)
  openNavigation(app)
}

const openNavigation = (app) => {
  const { latitude, longitude } = props
  let url = ''
  if (invalidCoords) {
    return
  }

  switch (app) {
    case 'google':
      url = `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`
      break
    case 'waze':
      url = `https://waze.com/ul?ll=${latitude},${longitude}&navigate=yes`
      break
    case 'apple':
      url = `http://maps.apple.com/?daddr=${latitude},${longitude}`
      break
    case 'osmand':
      url = `https://osmand.net/go?lat=${latitude}&lon=${longitude}`
      break
    case 'komoot':
      url = `https://www.komoot.de/plan/@${latitude},${longitude}?p[0]&p[1][loc]=${latitude},${longitude}`
      break
    case 'here':
      url = `https://www.here.com/directions/drive/mylocation/${latitude},${longitude}`
      break
  }

  if (url) window.open(url, '_blank')
}

onMounted(() => {
  const savedApp = localStorage.getItem(STORAGE_KEY)
  if (savedApp) {
    selectedApp.value = savedApp
  }
})
</script>

<style scoped>
.navigation-selector {
  display: inline-block;
}
.extra-small {
  height: 15px;
}
</style>

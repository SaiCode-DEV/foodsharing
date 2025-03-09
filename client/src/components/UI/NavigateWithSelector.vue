<template>
  <div class="navigation-selector">
    <b-button-group :vertical="vertical">
      <b-button
        variant="outline-primary"
        :size="small ? 'sm' : 'md'"
        :disabled="invalidCoords"
        @click="handleMainButtonClick"
        @mouseup="handleMouseUp"
      >
        <template v-if="selectedApp === null">
          <i class="fas fa-directions" />
        </template>
        <template v-else>
          <img
            :height="small ? '20em' : '30em'"
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
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { pulseError } from '@/script'

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
  small: {
    type: Boolean,
    default: false,
  },
})

const invalidCoords = !props.latitude || !props.longitude || (props.latitude === 0 && props.longitude === 0)

const isMobile = /Android|webOS|iPhone/i.test(navigator.userAgent)

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

if (isMobile) {
  APPS.unshift({
    name: 'native',
    icon: 'Compass.svg',
    displayName: i18n('navi.native'),
  })
}

const STORAGE_KEY = 'preferred_nav_app'
const selectedApp = ref(null)
const dropdownRef = ref(null)
const { confirmationDialogue } = useConfirmationDialogue()

const selectApp = async (app) => {
  const confirmed = await confirmationDialogue(
    i18n('navi.gdpr_warning', {
      provider: APPS.find(a => a.name === app).displayName,
    }),
    { title: i18n('legal.privacy_policy'), okTitle: i18n('legal.button.agree') },
  )

  if (confirmed) {
    selectedApp.value = app
    localStorage.setItem(STORAGE_KEY, app)
    openNavigation(app)
  }
}

const handleMainButtonClick = () => {
  const savedApp = localStorage.getItem(STORAGE_KEY)
  if (!savedApp) {
    dropdownRef.value?.show()
  } else {
    openNavigation(savedApp)
  }
}

const handleMouseUp = (event) => {
  if (event.button === 1) { // Middle click
    event.preventDefault()
    handleMainButtonClick()
  }
}

const openNavigation = (app) => {
  const { latitude, longitude } = props
  let url = ''
  if (invalidCoords) {
    return
  }

  switch (app) {
    case 'native':
      url = `geo:${latitude},${longitude}`
      break
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

  // Try native navigation first on mobile, fall back to google maps if it fails
  if (app === 'native' && isMobile) {
    const element = document.createElement('a')
    element.href = url
    element.click()

    // Fallback after a short delay if the geo: link wasn't handled
    setTimeout(() => {
      pulseError(i18n('navi.no_native_app'))
    }, 1000)
    return
  }

  if (url) window.open(url, '_blank')
}

onMounted(() => {
  const savedApp = localStorage.getItem(STORAGE_KEY)
  if (savedApp) {
    selectedApp.value = savedApp
  }

  if (isMobile) {
    const nativeApp = APPS.find(app => app.name === 'native')
    if (nativeApp) {
      APPS.splice(APPS.indexOf(nativeApp), 1)
      APPS.unshift(nativeApp)
    }
  }
})
</script>

<style scoped>
.navigation-selector {
  display: inline-block;
}
.extra-small {
  height: 18px;
}
.extra-small ::v-deep .btn {
  padding: 0 0.5rem;
  border-top-style: none;
}
</style>

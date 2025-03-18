<template>
  <div id="map-control-wrapper">
    <b-button
      id="map-control-collapse"
      variant="secondary"
      class="rounded-circle"
      @click="collapseControl"
    >
      <i class="fas fa-layer-group" />
    </b-button>

    <b-card
      id="map-legend"
      no-body
      :class="collapsedClass"
    >
      <b-card-body id="map-control" class="p-2">
        <b-list-group>
          <b-list-group-item
            v-for="markerType in visibleTypes"
            :key="markerType"
            class="p-0 border-0"
          >
            <b-button
              :ref="`button-${markerType}`"
              class="map-legend-entry w-100 text-left"
              :class="`${markerType} ${activeButtonClass(markerType)}`"
              @click="$emit('toggle-marker-type', markerType)"
            >
              <i :class="`fas fa-${markerTypes[markerType].icon}`" />
              {{ $i18n(markerTypes[markerType].label) }}
            </b-button>

            <StoreSpecifierSelection
              v-if="markerType === markerTypes.stores.name && selectedTypes.includes(markerType)"
              :selected-specifiers="selectedSpecifiers[markerType]"
              @update-specifier="(specifier, newValue) => $emit('update-marker-specifier', markerType, specifier, newValue)"
            />
            <UserSpecifierSelection
              v-if="markerType === markerTypes.users.name && selectedTypes.includes(markerType)"
              :selected-specifiers="selectedSpecifiers[markerType]"
              :regions="ambassadorRegions"
              @update-specifier="(specifier, newValue) => $emit('update-marker-specifier', markerType, specifier, newValue)"
            />
          </b-list-group-item>
        </b-list-group>
      </b-card-body>
    </b-card>
  </div>
</template>

<script>
import { MARKER_TYPES } from '@/stores/map'
import StoreSpecifierSelection from './SpecifierSelections/StoreSpecifierSelection.vue'
import UserSpecifierSelection from './SpecifierSelections/UserSpecifierSelection.vue'

export default {
  components: { StoreSpecifierSelection, UserSpecifierSelection },
  props: {
    visibleTypes: { type: Array, required: true },
    selectedTypes: { type: Array, required: true },
    selectedSpecifiers: { type: Object, required: true },
    ambassadorRegions: { type: Array, default: () => [] },
  },
  data () {
    return {
      isCollapsed: false,
      markerTypes: MARKER_TYPES,
    }
  },
  computed: {
    collapsedClass () {
      return this.isCollapsed ? 'collapsed' : ''
    },
  },
  methods: {
    activeButtonClass (name) {
      return this.selectedTypes.includes(name) ? 'active' : ''
    },
    collapseControl () {
      this.isCollapsed = !this.isCollapsed
    },
  },
}
</script>

<style lang="scss" scoped>
#map-control-wrapper {
  height: 0;
  margin: 0;
  position: absolute;
  right: 16px;
  top: calc(var(--navbar-height) + 16px);
  z-index: 450;

  > div {
    position: relative;
  }
}

#map-control-collapse {
  height: 30px;
  width: 30px;
  margin: 0 0 5px auto;
  padding: 0;
  display: flex;
  justify-content: center;
  align-items: center;
  background-color: var(--fs-color-secondary-500);
  border-color: transparent;
}

#map-legend {
  --size: 2rem;
  transition: opacity 0.2s ease-in-out;
  background: var(--fs-color-white);
  min-width: 280px;
  top: 0.5em;

  &.collapsed {
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s 0.2s, opacity 0.2s ease-in-out;
  }

  .map-legend-entry {
    display: flex;
    padding: 0.25rem 0.5rem;
    margin: 0.25rem;
    align-items: center;
    min-height: calc(var(--size) * 1.5);
    border: 0;
    font-weight: 600;
    font-size: 1rem;
    background: transparent;

    color: var(--fs-color-primary-500);

    &:hover, &.active {
      color: var(--type-color);
    }

    &.baskets { --type-color: var(--fs-color-type-baskets); }
    &.stores { --type-color: var(--fs-color-type-stores); }
    &.foodsharepoints { --type-color: var(--fs-color-type-foodsharepoints); }
    &.communities { --type-color: var(--fs-color-type-communities); }
    &.users { --type-color: var(--fs-color-type-users); }
    &.events { --type-color: var(--fs-color-type-events); }

    &:hover {
      background-color: var(--fs-color-primary-100);
    }

    i {
      font-size: 1rem;
      margin-left: .5rem;
      margin-right: 1rem;
      position: relative;
      color: var(--fs-color-light);
      &::before {
        position: relative;
        z-index: 2;
      }
      &::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: var(--size);
        height: var(--size);
        border-radius: 50%;
        z-index: 1;
        transform: translate(-50%, -50%);
        background-color: var(--type-color);
      }
    }

    &.active {
      color: var(--fs-color-light);
      background-color: var(--type-color);
      i {
        color: var(--type-color);
        &::after {
          background-color: var(--fs-color-white);
        }
      }
    }
  }
}
</style>

<style lang="scss">
.map-legend-font-size {
  font-size: 0.7rem;
}

.map-legend-selection {
  margin-left: .5rem;
}
</style>

<!-- The component that allows you to select the types of map markers. -->
<template>
  <div id="map-control-wrapper">
    <div
      id="map-control-collapse"
      class="ui-dialog ui-widget ui-widget-content"
      tabindex="-1"
      @click="collapseControl"
    >
      <i class="fas fa-layer-group" />
    </div>
    <div
      id="map-legend"
      class="ui-dialog ui-widget ui-widget-content ui-corner-all"
      :class="collapsedClass"
      tabindex="-1"
    >
      <div class="ui-dialog-content ui-widget-content">
        <ul id="map-control" class="linklist">
          <li
            v-for="type in markerTypes"
            :key="type.name"
          >
            <a
              v-if="visibleTypes.includes(type.name)"
              :ref="`button-${type.name}`"
              class="map-legend-entry"
              :class="`${type.name} ${activeButtonClass(type.name)}`"
              @click="toggleMarkerType(type.name)"
            >
              <i :class="`fas fa-${type.icon}`" /> {{ $i18n(type.label) }}
            </a>
            <div
              v-if="type.name === 'stores' && selectedTypes.includes(type.name)"
              class="map-legend-selection"
            >
              <label
                v-for="storeType in storeMarkerTypes"
                :key="storeType.name"
              >
                <input
                  type="checkbox"
                  name="viewopt[]"
                  :checked="selectedStoreTypes.includes(storeType.name)"
                  :value="storeType.name"
                  @click="toggleStoreType(storeType.name)"
                >
                {{ $i18n(storeType.label) }}
              </label>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import { MARKER_TYPES, STORE_MARKER_TYPES } from '@/stores/map'

export default {
  props: {
    visibleTypes: { type: Array, required: true },
    selectedTypes: { type: Array, required: true },
    selectedStoreTypes: { type: Array, required: true },
  },
  data () {
    return {
      isCollapsed: false,
      markerTypes: Object.values(MARKER_TYPES),
      storeMarkerTypes: Object.values(STORE_MARKER_TYPES),
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
    toggleMarkerType (name) {
      this.$emit('toggle-marker-type', name)
    },
    toggleStoreType (name) {
      this.$emit('toggle-store-marker-type', name)
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
  top: 160px;
  z-index: 450;

  @media (max-width: 575px) {
    top: 94px;
  }

  > div {
    position: relative;
  }
}

#map-control-collapse {
  cursor: pointer;
  background-color: var(--fs-color-secondary-500);
  color: var(--fs-color-light);
  position: absolute;
  height: 30px;
  width: 30px;
  border-radius: 100px;
  margin: 0 0 5px auto;
  display: flex;
  justify-content: center;
  align-items: center;
}

#map-control a {
  cursor: pointer;
}

#map-legend {
  --size: 2rem;
  transition: opacity 0.2s ease-in-out;

  &.collapsed {
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s 0.2s, opacity 0.2s ease-in-out;
  }

  .ui-dialog-content {
    padding: .5rem;
    min-width: 280px;
  }

  .map-legend-selection {
    margin: 0;
    padding: 0.5rem;

    label {
      width: 100%;
      cursor: pointer;
      display: block;
      font-size: 11px;
    }
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

    &.baskets {
      i::after {
        background-color: var(--fs-color-type-baskets);
      }
    }
    &.stores {
      i::after {
        background-color: var(--fs-color-type-stores);
      }
    }
    &.foodsharepoints {
      i::after {
        background-color: var(--fs-color-type-foodsharepoints);
      }
    }
    &.communities {
      i::after {
        background-color: var(--fs-color-type-communities);
      }
    }
    &:hover {
      background-color: var(--fs-color-primary-100);
      &.baskets {
        color: var(--fs-color-type-baskets);
      }
      &.stores {
        color: var(--fs-color-type-stores);
      }
      &.foodsharepoints {
        color: var(--fs-color-type-foodsharepoints);
      }
      &.communities {
        color: var(--fs-color-type-communities);
      }
    }

    i {
      color: var(--fs-color-light);
      font-size: 1rem;
      margin-left: .5rem;
      margin-right: 1rem;
      position: relative;

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
        background-color: var(--fs-color-white);
        border-radius: 50%;
        z-index: 1;
        transform: translate(-50%, -50%);
      }
    }

    &.active {
      i::after {
        background-color: var(--fs-color-white);
      }
      &.baskets {
        color: var(--fs-color-light);
        background-color: var(--fs-color-type-baskets);
        i {
          color: var(--fs-color-type-baskets);
        }
      }
      &.stores {
        color: var(--fs-color-light);
        background-color: var(--fs-color-type-stores);
        i {
          color: var(--fs-color-type-stores);
        }
      }
      &.foodsharepoints {
        color: var(--fs-color-light);
        background-color: var(--fs-color-type-foodsharepoints);
        i {
          color: var(--fs-color-type-foodsharepoints);
        }
      }
      &.communities {
        color: var(--fs-color-light);
        background-color: var(--fs-color-type-communities);
        i {
          color: var(--fs-color-type-communities);
        }
      }
    }
  }
}
</style>

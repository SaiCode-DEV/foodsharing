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
              v-if="type.visible"
              :ref="`button-${type.name}`"
              class="map-legend-entry"
              :class="`${type.name} ${activeButtonClass(type)}`"
              @click="toggleMarkerType(type)"
            >
              <i class="fas" :class="type.icon" /> {{ $i18n(type.label) }}
            </a>
            <div
              v-if="type.name === 'stores' && type.selected"
              class="map-legend-selection"
            >
              <label
                v-for="storeType in storeMarkerTypes"
                :key="storeType.name"
              >
                <input
                  type="checkbox"
                  name="viewopt[]"
                  :checked="storeType.selected"
                  :value="storeType.name"
                  @click="toggleStoreType(storeType)"
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
import { loadMarker } from '@php/Modules/Map/Map'

export default {
  props: {
    maySeeStores: { type: Boolean, default: false },
  },
  data () {
    return {
      isCollapsed: false,
      markerTypes: [
        { name: 'baskets', label: 'terminology.baskets', icon: 'fa-shopping-basket', visible: true, selected: true },
        { name: 'stores', label: 'menu.entry.stores', icon: 'fa-shopping-cart', visible: this.maySeeStores, selected: false },
        { name: 'foodsharepoints', label: 'terminology.fsp', icon: 'fa-recycle', visible: true, selected: false },
        { name: 'communities', label: 'menu.entry.regionalgroups', icon: 'fa-users', visible: true, selected: false },
      ],
      storeMarkerTypes: [
        { name: 'allebetriebe', label: 'store.bread', selected: false },
        { name: 'needhelp', label: 'menu.entry.helpwanted', selected: true },
        { name: 'needhelpinstant', label: 'menu.entry.helpneeded', selected: true },
        { name: 'nkoorp', label: 'menu.entry.other_stores', selected: false },
        { name: 'mine', label: 'map.filters.my_stores', selected: false },
      ],
    }
  },
  computed: {
    collapsedClass () {
      return this.isCollapsed ? 'collapsed' : ''
    },
    selectedTypes () {
      return this.markerTypes.filter(type => type.selected).map(type => type.name)
    },
    selectedStoreTypes () {
      return this.storeMarkerTypes.filter(type => type.selected).map(type => type.name)
    },
  },
  mounted () {
    loadMarker(this.selectedTypes, this.selectedStoreTypes)
  },
  methods: {
    activeButtonClass (type) {
      return type.selected ? 'active' : ''
    },
    toggleMarkerType (type) {
      type.selected = !type.selected
      loadMarker(this.selectedTypes, this.selectedStoreTypes)
    },
    toggleStoreType (storeType) {
      storeType.selected = !storeType.selected

      if (storeType.name === this.storeMarkerTypes[0].name) {
        if (this.storeMarkerTypes[0].selected) {
          for (let i = 1; i < this.storeMarkerTypes.length; i++) {
            this.storeMarkerTypes[i].selected = false
          }
        } else {
          this.storeMarkerTypes[1].selected = true
          this.storeMarkerTypes[2].selected = true
        }
      } else {
        this.storeMarkerTypes[0].selected = false
      }

      if (this.selectedStoreTypes.length < 1) {
        this.storeMarkerTypes[0].selected = true
      }

      loadMarker(this.selectedTypes, this.selectedStoreTypes)
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

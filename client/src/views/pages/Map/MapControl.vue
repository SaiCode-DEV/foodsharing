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
          <li v-for="markerType in Object.values(markerTypes)" :key="markerType.name">
            <a
              v-if="visibleTypes.includes(markerType.name)"
              :ref="`button-${markerType.name}`"
              class="map-legend-entry"
              :class="`${markerType.name} ${activeButtonClass(markerType.name)}`"
              @click="$emit('toggle-marker-type', markerType.name)"
            >
              <i :class="`fas fa-${markerType.icon}`" /> {{ $i18n(markerType.label) }}
            </a>
            <div
              v-if="markerType === markerTypes.stores && selectedTypes.includes(markerType.name)"
              class="map-legend-selection"
            >
              <b-form-group
                v-for="[selectType, options] in Object.entries(storeMarkerSelectTypes)"
                :key="selectType"
                label-cols="4"
                :label="$i18n(`map.filters.stores.${selectType}.label`) + ':'"
                :label-for="`${selectType}-select`"
              >
                <b-select
                  :id="`${selectType}-select`"
                  :value.sync="selectedStoreTypes[selectType]"
                  class="w-100"
                  size="sm"
                  :options="options.map(x => ({ text: $i18n(`map.filters.stores.${selectType}.${x}`), value: x }))"
                  @change="newValue => $emit('select-store-marker-type', selectType, newValue)"
                />
              </b-form-group>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import { MARKER_TYPES, STORE_MARKER_SELECT_TYPES } from '@/stores/map'

export default {
  props: {
    visibleTypes: { type: Array, required: true },
    selectedTypes: { type: Array, required: true },
    selectedStoreTypes: { type: Object, required: true },
  },
  data () {
    return {
      isCollapsed: false,
      markerTypes: MARKER_TYPES,
      storeMarkerSelectTypes: STORE_MARKER_SELECT_TYPES,
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
    ::v-deep {
      label, select {
        font-size: 12px !important;
      }
      .form-row {
        margin-bottom: 0.5rem;
      }
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

    &.baskets { --type-color: var(--fs-color-type-baskets); }
    &.stores { --type-color: var(--fs-color-type-stores); }
    &.foodsharepoints { --type-color: var(--fs-color-type-foodsharepoints); }
    &.communities { --type-color: var(--fs-color-type-communities); }

    &:hover {
      background-color: var(--fs-color-primary-100);
      color: var(--type-color);
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

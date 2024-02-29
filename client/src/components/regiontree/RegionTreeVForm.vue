<!-- Wrapper class that allows using the RegionTree component in v-forms. -->
<template>
  <div id="input-wrapper" class="bootstrap">
    <label
      v-if="title"
      class="wrapper-label ui-widget"
    >
      {{ title }}
    </label>
    <div>
      {{ selectedRegion.name }}
      <b-link
        v-if="!disabled"
        class="btn btn-sm btn-secondary ml-2"
        @click="openModal"
      >
        {{ $i18n('region.change') }}
      </b-link>
    </div>

    <b-modal
      ref="regionTreeModal"
      :title="$i18n(modalTitle)"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.apply')"
      :ok-disabled="tmpSelectedRegion === null"
      scrollable
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="onModalClosed"
    >
      <region-tree
        :selectable-region-types="selectableRegionTypes"
        @change="onRegionSelected"
      />
    </b-modal>

    <div class="element-wrapper">
      <input
        :name="inputName"
        :value="selectedRegion.id"
        type="hidden"
      >
    </div>
  </div>
</template>

<script>
import RegionTree from './RegionTree'
import { BLink, BModal } from 'bootstrap-vue'

export default {
  components: { BLink, BModal, RegionTree },
  props: {
    title: {
      type: String,
      default: null,
    },
    modalTitle: {
      type: String,
      default: 'terminology.homeRegion',
    },
    inputName: {
      type: String,
      required: true,
    },
    value: {
      type: Object,
      default: function () {
        return {
          id: 0,
          name: 'Root',
        }
      },
    },
    // if not null, only these types of regions can be selected
    selectableRegionTypes: { type: Array, default: null },
    disabled: {
      type: Boolean,
      default: false,
    },
  },
  data () {
    return {
      tmpSelectedRegion: null,
      selectedRegion: this.value,
    }
  },
  methods: {
    onRegionSelected (region) {
      this.tmpSelectedRegion = region
    },
    onModalClosed (e) {
      this.selectedRegion = this.tmpSelectedRegion
      this.$emit('input', this.tmpSelectedRegion)
    },
    openModal () {
      this.tmpSelectedRegion = null
      this.$refs.regionTreeModal.show()
    },
  },
}
</script>

<style lang="scss">

</style>

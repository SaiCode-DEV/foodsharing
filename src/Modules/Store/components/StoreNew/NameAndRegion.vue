<template>
  <div class="list-group-item">
    <b-form-input
      v-model.lazy="v$.name.$model"
      :class="{ 'is-invalid': v$.name.$error}"
      :placeholder="$t('storeedit.store_name_placeholder')"
      @input="$emit('update:name', $event)"
    />
    <span v-if="v$.name.$error">{{ $t('storeedit.name_error') }}</span>
    <b-input-group class="pt-2 pb-2">
      <b-form-input
        :value="region.name"
        type="text"
        :disabled="true"
      />
      <b-input-group-append>
        <b-button
          variant="outline-secondary"
          @click="$refs.storeRegionTree.openModal()"
        >
          <i class="far fa-edit" />
        </b-button>
      </b-input-group-append>
    </b-input-group>

    <b-button
      class="float-right"
      variant="primary"
      :disabled="v$.$invalid"
      @click="redirect()"
    >
      {{ $t('button.next') }}
    </b-button>
    <region-tree-modal
      ref="storeRegionTree"
      :value="region"
      modal-title="storeview.select_related_region"
      input-name="regionId"
      :selectable-region-types="selectableRegionTypes"
      :disabled="!editMode"
      class="my-3"
      @input="updateStoreRegion"
    />
  </div>
</template>

<script>
import RegionTreeModal from '@/components/regiontree/RegionTreeModal.vue'
import { SELECTABLE_REGION_TYPES } from '@/stores/regions'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength } from '@vuelidate/validators'

export default {
  components: { RegionTreeModal },
  props: {
    chosenRegion: { type: Object, required: true },
  },
  validations: {
    name: { required, minLength: minLength(3) },
  },
  setup () {
    return {
      v$: useVuelidate(),
    }
  },
  data () {
    return {
      name: null,
      editMode: true,
      region: this.chosenRegion,
    }
  },
  computed: {
    selectableRegionTypes () {
      return SELECTABLE_REGION_TYPES
    },
  },
  methods: {
    updateStoreRegion (region) {
      this.region.id = region.states.id
      this.region.name = region.data.text
    },
    redirect () {
      this.v$.$touch()
      if (!this.v$.$invalid) {
        this.$emit('update:region', this.region)
        this.$emit('next')
      }
    },
  },
}
</script>

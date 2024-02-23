<template>
  <div class="list-group-item">
    <b-form-input
      v-model.lazy="$v.name.$model"
      :class="{ 'is-invalid': $v.name.$error}"
      :placeholder="$i18n('storeedit.store_name_placeholder')"
      @input="$emit('update:name', $event)"
    />
    <span v-if="$v.name.$error">{{ $i18n('storeedit.name_error') }}</span>
    <region-tree-v-form
      v-model="region"
      modal-title="storeview.select_related_region"
      input-name="regionId"
      :selectable-region-types="selectableRegionTypes"
      :disabled="!editMode"
      class="my-3"
    />

    <b-button
      class="float-right"
      variant="primary"
      :disabled="$v.$invalid"
      @click="redirect()"
    >
      {{ $i18n('button.next') }}
    </b-button>
  </div>
</template>

<script>
import RegionTreeVForm from '@/components/regiontree/RegionTreeVForm.vue'
import { REGION_UNIT_TYPE } from '@/stores/regions'
import { required, minLength } from 'vuelidate/lib/validators'

export default {
  components: { RegionTreeVForm },
  props: {
    chosenRegion: { type: Object, required: true },
  },
  validations: {
    name: { required, minLength: minLength(3) },
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
      return [REGION_UNIT_TYPE.CITY, REGION_UNIT_TYPE.BIG_CITY, REGION_UNIT_TYPE.PART_OF_TOWN]
    },
  },
  methods: {
    redirect () {
      this.$v.$touch()
      if (!this.$v.$invalid) {
        this.$emit('update:region', this.region)
        this.$emit('next')
      }
    },
  },
}
</script>

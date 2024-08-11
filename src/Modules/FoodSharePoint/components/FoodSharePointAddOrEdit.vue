<template>
  <div>
    <b-container class="bg-white p-4">
      <b-form>
        <b-form-group
          label="In welchem Bezirk befindet sich der Fairteiler?"
          label-for="district-select"
          required
        >
          <b-form-select
            id="district-select"
            v-model="choosenRegion"
            :options="filteredRegions"
            required
          />
        </b-form-group>

        <b-form-group label="Name" label-for="name-input">
          <b-form-input
            id="name-input"
            v-model="foodSharePointName"
            type="text"
            required
            placeholder="Geben Sie den Namen ein"
          />
        </b-form-group>

        <b-form-group label="Beschreibung" label-for="description-md">
          <MarkdownInput />
        </b-form-group>

        <b-form-group label="Bild" label-for="name-input">
          <FileUploadVForm
            :is-image="true"
            :img-height="900"
            :img-width="400"
            :initial-value="foodSharePointData.pic.head"
          />
        </b-form-group>

        <b-form-group label="Adress-/Standort-Suche" label-for="name-input">
          <LeafletLocationSearchVForm :coordinates="coordinates" :zoom="zoom" />
        </b-form-group>

        <label for="tags-basic">Foodsaver:innen, die Ansprechpersonen für den Fairteiler sind</label>
        <multi-user-search-input
          v-model="choosenManagers"
          :region-id="choosenRegion"
          button-icon="fa-user-plus"
        />

        <b-button type="submit" variant="primary">
          Absenden
        </b-button>
      </b-form>
    </b-container>
  </div>
</template>

<script>
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import FileUploadVForm from '@/components/upload/FileUploadVForm.vue'
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm'
import MultiUserSearchInput from '@/components/MultiUserSearchInput.vue'

export default {
  name: 'FoodSharePointAddOrEdit',
  components: { MultiUserSearchInput, MarkdownInput, FileUploadVForm, LeafletLocationSearchVForm },
  props: {
    regions: { type: Object, required: true },
    foodSharePointData: { type: Object, default: () => {} },
    managers: { type: Array, default: () => [] },
  },
  data () {
    return {
      zoom: 17,
      choosenRegion: this.foodSharePointData.bezirk_id ?? [],
      choosenManagers: [],
      foodSharePointName: this.foodSharePointData.name ?? '',
      coordinates: { lat: this.foodSharePointData.lat, lon: this.foodSharePointData.lon },
    }
  },
  computed: {
    filteredRegions () {
      return Object.values(this.regions).map(region => ({
        value: region.id,
        text: region.name,
      }))
    },
  },
  created () {
    this.setChoosenManagers()
  },
  methods: {
    setChoosenManagers () {
      if (this.managers && this.managers.length > 0) {
        this.choosenManagers = Object.values(this.managers).map(manager => manager.id)
      }
    },
  },
}
</script>

<style scoped lang="scss">

</style>

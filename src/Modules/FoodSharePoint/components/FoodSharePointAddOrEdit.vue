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
            v-model="foodSharePointStore.foodSharePoint.regionId"
            :options="regionOptions"
            required
          />
        </b-form-group>

        <b-form-group label="Name" label-for="name-input">
          <b-form-input
            id="name-input"
            v-model="foodSharePointStore.foodSharePoint.name"
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
            :initial-value="foodSharePointStore.foodSharePoint.picture"
          />
        </b-form-group>

        <b-form-group label="Adress-/Standort-Suche" label-for="name-input">
          <LeafletLocationSearchVForm
            :coordinates="{ lat: foodSharePointStore.foodSharePoint.lat, lon: foodSharePointStore.foodSharePoint.lon }"
            :zoom="zoom"
          />
        </b-form-group>

        <label for="tags-basic">Foodsaver:innen, die Ansprechpersonen für den Fairteiler sind</label>
        <multi-user-search-input
          v-model="foodSharePointStore.foodSharePoint.followers.manager"
          :region-id="foodSharePointStore.foodSharePoint.regionId"
          button-icon="fa-user-plus"
          :is-value-object="true"
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
import { useFoodSharePointStore } from '@/stores/foodSharePoint'
import { useRegionStore } from '@/stores/regions'

const foodSharePointStore = useFoodSharePointStore()
const regionStore = useRegionStore()
export default {
  name: 'FoodSharePointAddOrEdit',
  components: { MultiUserSearchInput, MarkdownInput, FileUploadVForm, LeafletLocationSearchVForm },
  setup () {
    return {
      foodSharePointStore,
    }
  },
  data () {
    return {
      foodSharePointId: null,
      regionId: null,
      zoom: 17,
      choosenRegion: null,
      coordinates: null,
    }
  },
  computed: {
    regionOptions () {
      return regionStore.regions.map(region => ({
        value: region.id,
        text: region.name,
      }))
    },
  },
  created () {
    const url = new URL(window.location.href)
    const searchParams = new URLSearchParams(url.search)
    this.regionId = parseInt(searchParams.get('bid'))
    this.foodSharePointId = parseInt(searchParams.get('id'))
    console.log('foodSharePointId', this.foodSharePointId)
    foodSharePointStore.fetchFoodSharePoint(this.foodSharePointId)
    console.log('foodSharePointStore', foodSharePointStore)
  },
}
</script>

<style scoped lang="scss">

</style>

<template>
  <div class="card mb-3 rounded">
    <div
      class="card-header text-white bg-primary"
    >
      {{ $t('ageBandList.header_for_district', {bezirk: regionName}) }}
    </div>
    <div>
      <b-tabs
        pills
        card
      >
        <b-tab
          :title="$t('ageBandList.district_tab')"
          active
          @click="reloadAgeBandDataTab"
        >
          <b-table
            :fields="fields"
            :items="ageBandDataTab"
            :sort-by="sortBy"
            striped
            hover
            small
            caption-top
            :busy="isAgeBandDataLoading"
          />
        </b-tab>
        <b-tab
          :title="$t('ageBandList.home_district_tab')"
          @click="reloadAgeBandHomeDistrictDataTab"
        >
          <b-table
            :fields="fields"
            :items="ageBandDataHomeDistrictTab"
            :sort-by="sortBy"
            striped
            hover
            small
            caption-top
            :busy="isAgeBandHomeDistrictDataLoading"
          />
        </b-tab>
      </b-tabs>
    </div>
  </div>
</template>

<script>

import { BTable, BTabs, BTab } from 'bootstrap-vue'
import { getRegionAgeBandData } from '@/api/statistics'

export default {
  components: { BTable, BTabs, BTab },
  props: {
    regionId: { type: Number, required: true },
    regionName: {
      type: String,
      default: '',
    },
  },
  data () {
    return {
      isAgeBandDataLoading: false,
      isAgeBandHomeDistrictDataLoading: false,
      ageBandDataTab: [],
      ageBandDataHomeDistrictTab: [],
      sortBy: 'ageBand',
      fields: [{
        key: 'ageBand',
        label: this.$t('ageBandList.ageBand'),
        sortable: true,
      },
      {
        key: 'numberOfAgeBand',
        label: this.$t('ageBandList.NumberOfAgeband'),
        sortable: true,
      },
      ],
    }
  },
  async created () {
    await this.asyncAgeBandData()
    this.isAgeBandHomeDistrictDataLoading = false
  },
  methods: {
    async asyncAgeBandData () {
      this.isAgeBandDataLoading = true
      try {
        console.log('asyncAgeBandData called')
        this.ageBandDataTab = await getRegionAgeBandData(this.regionId, false)
      } catch (error) {
        console.error('Error fetching ageBandDataTab:', error)
      }
      this.isAgeBandDataLoading = false
    },
    async asyncAgeBandHomeDistrictData () {
      this.isAgeBandHomeDistrictDataLoading = true
      try {
        // Make the API request to fetch gender data for the specified region
        this.ageBandDataHomeDistrictTab = await getRegionAgeBandData(this.regionId, true)
      } catch (error) {
        console.error('Error fetching asyncAgeBandHomeDistrictData:', error)
      }
      this.isAgeBandHomeDistrictDataLoading = false
    },
    async reloadAgeBandDataTab () {
      if (this.ageBandDataTab.length === 0) {
        await this.asyncAgeBandData()
      }
    },
    async reloadAgeBandHomeDistrictDataTab () {
      if (this.ageBandDataHomeDistrictTab.length === 0) {
        await this.asyncAgeBandHomeDistrictData()
      }
    },
  },
}
</script>

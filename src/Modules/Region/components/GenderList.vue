<template>
  <div class="card mb-3 rounded">
    <div
      class="card-header text-white bg-primary"
    >
      {{ $t('genderlist.header_for_district', {bezirk: regionName}) }}
    </div>
    <div>
      <b-card no-body>
        <b-tabs
          pills
          card
        >
          <b-tab
            :title="$t('genderlist.district_tab')"
            active
            @click="reloadGenderDataTab"
          >
            <b-table
              :fields="fields"
              :items="genderDataTab"
              :sort-by="sortBy"
              :sort-desc="sortDesc"
              striped
              hover
              small
              caption-top
              :busy="isGenderDataLoading"
            >
              <template slot="table-caption">
                {{ $t('genderlist.gender_district_table_caption') }}
              </template>
            </b-table>
          </b-tab>
          <b-tab
            :title="$t('genderlist.home_district_tab')"
            @click="reloadGenderHomeDistrictDataTab"
          >
            <b-table
              :fields="fields"
              :items="genderDataHomeDistrictTab"
              :sort-by="sortBy"
              :sort-desc="sortDesc"
              striped
              hover
              small
              caption-top
              :busy="isGenderHomeDistrictDataLoading"
            >
              <template slot="table-caption">
                {{ $t('genderlist.gender_home_district_table_caption') }}
              </template>
            </b-table>
          </b-tab>
        </b-tabs>
      </b-card>
    </div>
  </div>
</template>

<script>

import { BCard, BTable, BTabs, BTab } from 'bootstrap-vue'
import { getRegionGenderData } from '@/api/statistics'
export default {
  components: { BCard, BTable, BTabs, BTab },
  props: {
    regionId: { type: Number, required: true },
    regionName: {
      type: String,
      default: '',
    },
  },
  data () {
    return {
      isGenderDataLoading: false,
      isGenderHomeDistrictDataLoading: false,
      genderDataTab: [],
      genderDataHomeDistrictTab: [],
      sortBy: 'gender',
      sortDesc: true,
      fields: [
        {
          key: 'gender',
          label: this.$t('genderlist.gender_table_header'),
          formatter: item => {
            switch (item) {
              case 0:
                return this.$t('genderlist.gender_not_selected')
              case 1:
                return this.$t('genderlist.gender_male')
              case 2:
                return this.$t('genderlist.gender_female')
              case 3 :
                return this.$t('genderlist.gender_divers')
              default :
                return this.$t('genderlist.gender_not_selected')
            }
          },
          sortable: true,
        },
        {
          key: 'numberOfGender',
          label: this.$t('genderlist.number_table_header'),
          sortable: true,
        },
      ],
    }
  },
  async created () {
    await this.asyncGenderData()
    this.isGenderHomeDistrictDataLoading = false
  },
  methods: {
    async asyncGenderData () {
      this.isGenderDataLoading = true
      try {
        console.log('asyncGenderData called')
        this.genderDataTab = await getRegionGenderData(this.regionId, false)
      } catch (error) {
        console.error('Error fetching genderDataTab:', error)
      }
      this.isGenderDataLoading = false
    },
    async asyncGenderHomeDistrictData () {
      this.isGenderHomeDistrictDataLoading = true
      try {
        // Make the API request to fetch gender data for the specified region
        this.genderDataHomeDistrictTab = await getRegionGenderData(this.regionId, true)
      } catch (error) {
        console.error('Error fetching asyncGenderHomeDistrictData:', error)
      }
      this.isGenderHomeDistrictDataLoading = false
    },
    async reloadGenderDataTab () {
      if (this.genderDataTab.length === 0) {
        await this.asyncGenderData()
      }
    },
    async reloadGenderHomeDistrictDataTab () {
      if (this.genderDataHomeDistrictTab.length === 0) {
        await this.asyncGenderHomeDistrictData()
      }
    },
  },
}
</script>

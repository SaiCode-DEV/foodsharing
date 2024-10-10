<template>
  <div class="card mb-3 rounded">
    <div
      class="card-header text-white bg-primary"
    >
      {{ $i18n('pickuplist.header_for_district', {bezirk: regionName}) }}
    </div>
    <div>
      <b-tabs
        pills
        card
      >
        <b-tab
          :title="$i18n('pickuplist.day_tab')"
        >
          <b-pagination
            v-model="currentPageDaily"
            :total-rows="pickupData.daily.length"
            :per-page="perPage"
            aria-controls="pickupDaily-table"
          />
          <b-table
            id="pickupDaily-table"
            :current-page="currentPageDaily"
            :per-page="perPage"
            :fields="fields"
            :items="pickupData.daily"
            :sort-by="sortBy"
            :sort-desc="sortDesc"
            striped
            hover
            small
            bordered
            responsive
          />
        </b-tab>
        <b-tab
          :title="$i18n('pickuplist.week_tab')"
        >
          <b-pagination
            v-model="currentPageWeekly"
            :total-rows="pickupData.weekly.length"
            :per-page="perPage"
            aria-controls="pickupWeekly-table"
          />
          <b-table
            id="pickupWeekly-table"
            :fields="fields"
            :items="pickupData.weekly"
            :current-page="currentPageWeekly"
            :per-page="perPage"
            :sort-by="sortBy"
            :sort-desc="sortDesc"
            striped
            hover
            small
            bordered
            responsive
          />
        </b-tab>
        <b-tab
          :title="$i18n('pickuplist.month_tab')"
        >
          <b-pagination
            v-model="currentPageMonthly"
            :total-rows="pickupData.monthly.length"
            :per-page="perPage"
            aria-controls="pickupMonthly-table"
          />
          <b-table
            :fields="fields"
            :items="pickupData.monthly"
            :current-page="currentPageMonthly"
            :per-page="perPage"
            :sort-by="sortBy"
            :sort-desc="sortDesc"
            striped
            hover
            small
            bordered
            responsive
          />
        </b-tab>
        <b-tab
          :title="$i18n('pickuplist.year_tab')"
        >
          <b-pagination
            v-model="currentPageYearly"
            :total-rows="pickupData.yearly.length"
            :per-page="perPage"
            aria-controls="pickupYearly-table"
          />
          <b-table
            :fields="fields"
            :items="pickupData.yearly"
            :current-page="currentPageYearly"
            :per-page="perPage"
            :sort-by="sortBy"
            :sort-desc="sortDesc"
            striped
            hover
            small
            bordered
            responsive
          />
        </b-tab>
      </b-tabs>
    </div>
  </div>
</template>

<script>

import { BPagination, BTable, BTabs, BTab } from 'bootstrap-vue'
import { getRegionPickupStatisticsData } from '@/api/statistics'

export default {
  components: { BTable, BTabs, BTab, BPagination },
  props: {
    regionName: { type: String, default: '' },
    regionId: { type: Number, required: true },
  },
  data () {
    return {
      isLoading: false,
      pickupData: {
        daily: [],
        weekly: [],
        monthly: [],
        yearly: [],
      },
      sortBy: 'date',
      sortDesc: true,
      currentPageDaily: 1,
      currentPageWeekly: 1,
      currentPageMonthly: 1,
      currentPageYearly: 1,
      perPage: 14,
      fields: [
        {
          key: 'date',
          label: this.$i18n('pickuplist.time_table_header'),
          sortable: true,
        },
        {
          key: 'numberOfStores',
          label: this.$i18n('pickuplist.NumberOfStores_table_header'),
          sortable: true,
        },
        {
          key: 'numberOfPickups',
          label: this.$i18n('pickuplist.NumberOfAppointments_table_header'),
          sortable: true,
        },
        {
          key: 'numberOfSlots',
          label: this.$i18n('pickuplist.NumberOfSlots_table_header'),
          sortable: true,
        },
        {
          key: 'numberOfFoodsavers',
          label: this.$i18n('pickuplist.NumberOfFoodSavers_table_header'),
          sortable: true,
        },
      ],
    }
  },
  async mounted () {
    this.isLoading = true
    try {
      this.pickupData = await getRegionPickupStatisticsData(this.regionId)
    } catch (error) {
      console.error('Error fetching region statics data:', error)
    }
    this.isLoading = false
  },
}
</script>

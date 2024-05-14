<template>
  <div class="card mb-3 rounded">
    <div class="text-right">
      <Info
        info-key="profileCommitmentsStat"
      />
    </div>
    <div v-if="commitmentsStats[0].respActStores > 0">
      {{ $i18n('profile.commitments_stat.respActStores', { count: commitmentsStats[0].respActStores }) }} <i class="fas fa-shopping-cart" />
      <p />
    </div>
    <b-tabs>
      <template
        v-for="(item,index) in commitmentsStats"
      >
        <b-tab
          v-if="DisplayTab(item)"
          :key="index"
          :title="$i18n('profile.commitments_stat.week_' + index)"
        >
          <div>
            <p /> {{ $i18n('profile.commitments_stat.weekDesc', { week: item.week, weekStart:item.beginWeek, weekEnd:item.endWeek }) }}
            <p />
          </div>
          <template
            v-if="item.data.length > 0"
          >
            <b-table
              :id="`item-table${index}`"
              :key="index"
              :fields="fields"
              :items="item.data"
              :sort-by="sortBy"
              :sort-desc="sortDesc"
              striped
              hover
              small
              bordered
              responsive
            />
          </template>
          <div v-if="item.securePickupWeek > 0 ">
            <i class="fas fa-leaf" />
            {{ $i18n('profile.commitments_stat.securePickupWeek', { count: item.securePickupWeek }) }}
          </div>
          <div v-if="item.eventsCreated > 0 ">
            <i class="fas fa-calendar-day" />
            {{ $i18n('profile.commitments_stat.eventsCreatedWeek', { count: item.eventsCreated }) }}
          </div>
          <div v-if="item.eventsParticipated[0].count > 0 ">
            <i class="fas fa-calendar-check" />
            {{ $i18n('profile.commitments_stat.eventsParticipatedWeek', { count: item.eventsParticipated[0].count, hour: item.eventsParticipated[0].duration_hours, minute: item.eventsParticipated[0].duration_minutes }) }}
          </div>
          <div v-if="item.baskets.offered[0].count > 0 ">
            <i class="fas fa-shopping-basket" />
            {{ $i18n('profile.commitments_stat.basketsOfferedWeek', { count: item.baskets.offered[0].count, weight: item.baskets.offered[0].weight} ) }}
          </div>
          <div v-if="item.baskets.shared > 0 ">
            <i class="fas fa-handshake" />
            {{ $i18n('profile.commitments_stat.basketsSharedWeek', { count: item.baskets.shared } ) }}
          </div>
        </b-tab>
      </template>
    </b-tabs>
    <div>
      <p />
    </div>
  </div>
</template>

<script>

import Info from '@/components/Help/Info.vue'

export default {
  components: { Info },
  props: {
    commitmentsStats: {
      type: Array,
      default: () => [],
    },
  },
  data () {
    return {
      sortBy: 'time',
      sortDesc: true,
      fields: [
        {
          key: 'districtName',
          label: this.$i18n('profile.commitments_stat.districtName'),
          sortable: true,
        },
        {
          key: 'categorieName',
          label: this.$i18n('profile.commitments_stat.categorieName'),
          sortable: true,
        },
        {
          key: 'pickupAmount',
          label: this.$i18n('profile.commitments_stat.pickupAmount'),
          sortable: true,
        },
        {
          key: 'pickupCount',
          label: this.$i18n('profile.commitments_stat.pickupCount'),
          sortable: true,
        },
      ],
    }
  },
  methods: {
    DisplayTab (check) {
      return check.data.length > 0 || check.securePickupWeek > 0 || check.eventsCreated > 0 || check.eventsParticipated > 0 || check.baskets.shared > 0 || check.baskets.offered[0].count > 0
    },
  },
}
</script>

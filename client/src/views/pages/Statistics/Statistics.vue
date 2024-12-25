<template>
  <div>
    <Container
      :title="$i18n('stats.title')"
      :collapsible="false"
      :wrap-contents="true"
    >
      <div class="statistics-grid">
        <b-card
          v-for="stat in stats"
          :key="stat.id"
          no-body
        >
          <b-card-body class="statistics-card">
            <span class="fa-stack fa-3x text-secondary mr-3">
              <i class="fas fa-circle fa-stack-2x" />
              <i :class="stat.icon" class="fas fa-stack-1x fa-inverse" />
            </span>
            <div>
              <h4 class="mb-0">
                {{ formatNumber(stat.value) }}<span class="text-nowrap">&thinsp;</span>{{ stat.unit }}
              </h4>
              <p class="mb-0">
                {{ stat.label }}
              </p>
            </div>
          </b-card-body>
        </b-card>
      </div>
    </Container>

    <div class="row mt-4">
      <div
        v-for="board in leaderboards"
        :key="board.title"
        class="col-md-6 mb-4"
      >
        <Container
          :title="$i18n(board.title)"
          :collapsible="false"
          :wrap-contents="true"
        >
          <b-card
            v-for="(item, index) in board.items"
            :key="item.name"
            no-body
            class="mb-2 stats-list"
          >
            <b-card-body class="d-flex align-items-center py-2">
              <h4 class="mb-0 mr-4 stat-place">
                {{ index + 1 }}.
              </h4>
              <div>
                <h4 class="mb-0">
                  {{ item.name }}
                </h4>
                <p class="mb-0 text-secondary">
                  {{ formatNumber(item.fetchWeight) }}<span class="text-nowrap">&thinsp;</span>{{ $i18n('profile.stats.weight') }}
                </p>
                <p class="mb-0">
                  {{ formatNumber(item.fetchCount) }}<span class="text-nowrap">&thinsp;</span>x {{ $i18n('profile.stats.fetch_count') }}
                </p>
              </div>
            </b-card-body>
          </b-card>
        </Container>
      </div>
    </div>
  </div>
</template>

<script>
import { getOverallStatistics } from '@/api/statistics'
import i18n from '@/helper/i18n'
import Container from '@/components/Container/Container.vue'

export default {
  components: {
    Container,
  },
  data () {
    return {
      stats: [],
      regionsActivity: {
        pickupOverAllTime: [],
      },
      foodsaverActivity: {
        pickupOverAllTime: [],
      },
    }
  },
  computed: {
    leaderboards () {
      return [
        {
          title: 'stats.leader.regions',
          items: this.regionsActivity.pickupOverAllTime,
        },
        {
          title: 'stats.leader.users',
          items: this.foodsaverActivity.pickupOverAllTime,
        },
      ]
    },
  },
  async created () {
    const data = await getOverallStatistics()
    this.stats = this.mapStatistics(data)
    this.regionsActivity = data.regionsActivity
    this.foodsaverActivity = data.foodsaverActivity
  },
  methods: {
    formatNumber (number) {
      // Check for invalid values and return 0 if invalid
      if (number === undefined || number === null || isNaN(number)) {
        return '0'
      }
      return new Intl.NumberFormat().format(number)
    },
    mapStatistics (data) {
      const stats = data?.generalStatistic || {}
      return [
        {
          id: 1,
          iconClass: 'stat_icon fetchweight',
          icon: 'fa-apple-alt',
          value: stats.fetchWeight,
          unit: 'kg',
          label: i18n('stats.total.weight'),
        },
        {
          id: 2,
          iconClass: 'stat_icon coorpcount',
          icon: 'fa-store-alt',
          value: stats.cooperationsCount,
          unit: '',
          label: i18n('stats.total.cooperations'),
        },
        {
          id: 3,
          iconClass: 'stat_icon fscount',
          icon: 'fa-user-check',
          value: stats.countAllFoodsaver,
          unit: '',
          label: i18n('stats.total.foodsaver'),
        },
        {
          id: 4,
          iconClass: 'stat_icon fscount2',
          icon: 'fa-users',
          value: stats.foodsaverCount,
          unit: '',
          label: i18n('stats.total.foodsharer'),
        },
        {
          id: 5,
          iconClass: 'stat_icon fetchcount',
          icon: 'fa-walking',
          value: stats.fetchCount,
          unit: '',
          label: i18n('stats.total.pickups'),
        },
        {
          id: 6,
          iconClass: 'stat_icon dailyfetchcount',
          icon: 'fa-people-carry',
          value: stats.avgDailyFetchCount,
          unit: '',
          label: i18n('stats.avg.pickups'),
        },
        {
          id: 7,
          iconClass: 'stat_icon totalbaskets',
          icon: 'fa-shopping-basket',
          value: stats.totalBaskets,
          unit: '',
          label: i18n('stats.total.baskets'),
        },
        {
          id: 8,
          iconClass: 'stat_icon avgWeeklyBaskets',
          icon: 'fa-shopping-basket',
          value: stats.avgWeeklyBaskets,
          unit: '',
          label: i18n('stats.avg.baskets'),
        },
        {
          id: 9,
          iconClass: 'stat_icon fetchweight',
          icon: 'fa-recycle',
          value: stats.countActiveFoodSharePoints,
          unit: '',
          label: i18n('stats.total.fsp'),
        },
      ]
    },
  },
}
</script>

<style lang="scss" scoped>
.statistics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  padding: 1rem;
}

.statistics-card {
  display: flex;
  flex-direction: row;

  span {
    flex-basis: 1;
    flex-shrink: 0;
  }
}

.stats-list {
  // gold, silver, bronze, rest
  &:nth-of-type(2) .stat-place{
    color: #f1c40f;
  }
  &:nth-of-type(3) .stat-place{
    color: #b4c8d6;
  }
  &:nth-of-type(4) .stat-place{
    color: #cd7f32;
  }
}
</style>

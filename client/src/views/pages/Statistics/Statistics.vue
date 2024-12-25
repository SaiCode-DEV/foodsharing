<template>
  <div>
    <WeightFancyCounter
      v-if="stats.length"
      :number="stats[0].value"
    />
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
                {{ formatNumber(stat.value, stat.unit) }}
              </h4>
              <p class="mb-0">
                {{ stat.label }}
              </p>
            </div>
          </b-card-body>
        </b-card>
      </div>
    </Container>

    <div class="mt-4">
      <Container
        :title="$i18n('stats.leader.regions')"
        :collapsible="false"
        :wrap-contents="true"
      >
        <div class="regions-podium">
          <template v-if="regionsActivity.pickupOverAllTime.length >= 3">
            <div class="podium-item silver">
              <div class="podium-content">
                <h3 class="mb-0">
                  2
                </h3>
                <h4 class="mb-0">
                  {{ regionsActivity.pickupOverAllTime[1].name }}
                </h4>
                <p class="mb-0">
                  {{ formatNumber(regionsActivity.pickupOverAllTime[1].fetchWeight) }}<span class="text-nowrap">&thinsp;</span>{{ $i18n('profile.stats.weight') }}
                </p>
                <p class="mb-0">
                  {{ formatNumber(regionsActivity.pickupOverAllTime[1].fetchCount) }}<span class="text-nowrap">&thinsp;</span>x {{ $i18n('profile.stats.fetch_count') }}
                </p>
              </div>
            </div>
            <div class="podium-item gold">
              <div class="podium-content">
                <h3 class="mb-0">
                  1
                </h3>
                <h4 class="mb-0">
                  {{ regionsActivity.pickupOverAllTime[0].name }}
                </h4>
                <p class="mb-0">
                  {{ formatNumber(regionsActivity.pickupOverAllTime[0].fetchWeight) }}<span class="text-nowrap">&thinsp;</span>{{ $i18n('profile.stats.weight') }}
                </p>
                <p class="mb-0">
                  {{ formatNumber(regionsActivity.pickupOverAllTime[0].fetchCount) }}<span class="text-nowrap">&thinsp;</span>x {{ $i18n('profile.stats.fetch_count') }}
                </p>
              </div>
            </div>
            <div class="podium-item bronze">
              <div class="podium-content">
                <h3 class="mb-0">
                  3
                </h3>
                <h4 class="mb-0">
                  {{ regionsActivity.pickupOverAllTime[2].name }}
                </h4>
                <p class="mb-0">
                  {{ formatNumber(regionsActivity.pickupOverAllTime[2].fetchWeight) }}<span class="text-nowrap">&thinsp;</span>{{ $i18n('profile.stats.weight') }}
                </p>
                <p class="mb-0">
                  {{ formatNumber(regionsActivity.pickupOverAllTime[2].fetchCount) }}<span class="text-nowrap">&thinsp;</span>x {{ $i18n('profile.stats.fetch_count') }}
                </p>
              </div>
            </div>
          </template>
        </div>

        <div class="regions-grid">
          <b-card
            v-for="(item, index) in regionsActivity.pickupOverAllTime.slice(3)"
            :key="item.name"
            no-body
            class="mb-2"
          >
            <b-card-body class="d-flex align-items-center py-2">
              <h4 class="mb-0 mr-4">
                {{ index + 4 }}.
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
        </div>
      </Container>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { getOverallStatistics } from '@/api/statistics'
import i18n, { locale } from '@/helper/i18n'
import Container from '@/components/Container/Container.vue'
import WeightFancyCounter from './WeightFancyCounter.vue'

const stats = ref([])
const regionsActivity = ref({
  pickupOverAllTime: [],
})
const foodsaverActivity = ref({
  pickupOverAllTime: [],
})

const formatNumber = (number, unit) => {
  if (number === undefined || number === null || isNaN(number)) {
    return '0'
  }

  const options = {
    notation: 'compact',
    maximumFractionDigits: 2,
  }

  if (unit === 'kg') {
    options.style = 'unit'
    options.unit = 'kilogram'
    options.unitDisplay = 'narrow'
  }

  return new Intl.NumberFormat(locale, options).format(number)
}

const mapStatistics = (data) => {
  const generalStats = data?.generalStatistic || {}
  return [
    {
      id: 1,
      iconClass: 'stat_icon fetchweight',
      icon: 'fa-apple-alt',
      value: generalStats.fetchWeight,
      unit: 'kg',
      label: i18n('stats.total.weight'),
    },
    {
      id: 2,
      iconClass: 'stat_icon coorpcount',
      icon: 'fa-store-alt',
      value: generalStats.cooperationsCount,
      unit: '',
      label: i18n('stats.total.cooperations'),
    },
    {
      id: 3,
      iconClass: 'stat_icon fscount',
      icon: 'fa-user-check',
      value: generalStats.foodsaverCount,
      unit: '',
      label: i18n('stats.total.foodsaver'),
    },
    {
      id: 4,
      iconClass: 'stat_icon fscount2',
      icon: 'fa-users',
      value: generalStats.countAllFoodsaver,
      unit: '',
      label: i18n('stats.total.foodsharer'),
    },
    {
      id: 5,
      iconClass: 'stat_icon fetchcount',
      icon: 'fa-walking',
      value: generalStats.fetchCount,
      unit: '',
      label: i18n('stats.total.pickups'),
    },
    {
      id: 6,
      iconClass: 'stat_icon dailyfetchcount',
      icon: 'fa-people-carry',
      value: generalStats.avgDailyFetchCount,
      unit: '',
      label: i18n('stats.avg.pickups'),
    },
    {
      id: 7,
      iconClass: 'stat_icon totalbaskets',
      icon: 'fa-shopping-basket',
      value: generalStats.totalBaskets,
      unit: '',
      label: i18n('stats.total.baskets'),
    },
    {
      id: 8,
      iconClass: 'stat_icon avgWeeklyBaskets',
      icon: 'fa-shopping-basket',
      value: generalStats.avgWeeklyBaskets,
      unit: '',
      label: i18n('stats.avg.baskets'),
    },
    {
      id: 9,
      iconClass: 'stat_icon fetchweight',
      icon: 'fa-recycle',
      value: generalStats.countActiveFoodSharePoints,
      unit: '',
      label: i18n('stats.total.fsp'),
    },
  ]
}

// Initialize data (replaces created hook)
const initializeData = async () => {
  const data = await getOverallStatistics()
  stats.value = mapStatistics(data)
  regionsActivity.value = data.regionsActivity
  foodsaverActivity.value = data.foodsaverActivity
}

initializeData()
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

.regions-podium {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  padding: 1rem;
  align-items: flex-end;

  .podium-item {
    text-align: center;
    color: #000;
    padding: 1rem;
    border-radius: 8px;

    &.gold {
      order: 2;
      min-height: 200px;
      background: linear-gradient( to bottom right, #c39738, #deb761, #c39738);
    }

    &.silver {
      order: 1;
      min-height: 170px;
      background: linear-gradient( to bottom right, #bcc6cc, #eee, #bcc6cc);
    }

    &.bronze {
      order: 3;
      min-height: 140px;
      background: linear-gradient( to bottom right, #cd7f32, #f0a500, #cd7f32);
    }

    .podium-content {
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
  }
}

.regions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  padding: 1rem;
}
</style>

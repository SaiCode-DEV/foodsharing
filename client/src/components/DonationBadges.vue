<template>
  <b-row class="justify-content-center text-center mt-4 mb-4">
    <b-col
      cols="12"
      md="4"
      class="mt-4 mb-4"
    >
      <div class="stat-circle-dotted mb-2 text-success">
        <div class="stat-icon">
          <i class="fas fa-users fa-2x" />
        </div>
      </div>
      <div class="stat-value h3">
        {{ $n(friendshipCircleDonators, 'text') }}
      </div>
      <div class="stat-label">
        {{ $t('donation_page.stats.friendship_circle_donators') }}
      </div>
    </b-col>
    <b-col
      cols="12"
      md="4"
      class="mt-4 mb-4"
    >
      <div class="stat-circle-dotted mb-2 text-success">
        <div class="stat-icon">
          <i class="fas fa-hand-holding-heart fa-2x" />
        </div>
      </div>
      <div class="stat-value h3">
        {{ $n(oneTimeDonators, 'text') }}
      </div>
      <div class="stat-label">
        {{ $t('donation_page.stats.one_time_donators') }}
      </div>
    </b-col>
    <b-col
      cols="12"
      md="4"
      class="mt-4 mb-4"
    >
      <div class="stat-circle-dotted mb-2 text-success">
        <div class="stat-icon">
          <i class="fas fa-user-friends fa-2x" />
        </div>
      </div>
      <div class="stat-value h3">
        {{ $n(countAllFoodsaver, 'text') }}
      </div>
      <div class="stat-label">
        {{ $t('donation_page.stats.all_donators') }}
      </div>
    </b-col>
  </b-row>
</template>

<script setup>
import { onMounted, computed } from 'vue'
import { useDonationStore } from '@/stores/donation'
import { useStatisticsStore } from '@/stores/statistics'

const donationStore = useDonationStore()
const statisticsStore = useStatisticsStore()

const countAllFoodsaver = computed(() => statisticsStore.countAllFoodsaver)
const friendshipCircleDonators = computed(() => donationStore.friendshipCircleDonators)
const oneTimeDonators = computed(() => donationStore.oneTimeDonators)

onMounted(() => {
  donationStore.fetchDonationData()
  statisticsStore.fetchOverallStatistics()
})
</script>

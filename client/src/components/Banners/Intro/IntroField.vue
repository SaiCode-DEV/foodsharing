<template>
  <div v-if="user" class="introfield">
    <Avatar
      :user="{ ...user, isSleeping }"
      :size="50"
      class="mr-2"
    />

    <div class="introfield__content">
      <h3
        class="introfield__title testing-intro-field"
        v-text="viewIsMD ? $t('dashboard.greeting', {name: user.firstname}) : $t('dashboard.greeting_short', {name: user.firstname})"
      />
      <p
        v-if="!isFoodsaver && !getHomeRegionName"
        class="introfield__description"
        v-text="$t('dashboard.foodsharer')"
      />
      <p
        v-if="!getHomeRegionName && stats.count > 0 && stats.weight > 0"
        class="introfield__description"
        v-text="$t('dashboard.foodsaver_amount', {pickups: stats.count, weight: stats.weight})"
      />
      <Markdown
        v-if="getHomeRegionName && stats.count > 0 && stats.weight > 0"
        classes="introfield__description"
        :source="$t('dashboard.full_subline', {pickups: stats.count, weight: stats.weight, region: getHomeRegionName})"
      />
      <p
        v-else-if="isFoodsaver && getHomeRegionName"
        class="introfield__description"
        v-text="$t('dashboard.homeRegion', {region: getHomeRegionName})"
      />
    </div>
  </div>
</template>
<script>
import { useUserStore } from '@/stores/user'
import Avatar from '@/components/Avatar/Avatar.vue'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import Markdown from '@/components/Markdown/Markdown.vue'

const userStore = useUserStore()

export default {
  components: { Avatar, Markdown },
  mixins: [MediaQueryMixin],
  props: {
    title: { type: String, default: 'dashboard.my.regions' },
  },
  setup () {
    return {
      userStore,
    }
  },
  computed: {
    user () {
      return userStore.getUser
    },
    isSleeping () {
      return userStore.isSleeping
    },
    stats () {
      return userStore.getStats
    },
    getHomeRegionName () {
      const regionHome = userStore.getHomeRegionName
      if (regionHome?.length > 0) {
        return regionHome
      }
      return null
    },
    isFoodsaver () {
      return userStore.isFoodsaver
    },
  },
}
</script>

<style lang="scss" scoped>
@import "@/scss/bootstrap-theme.scss";

.introfield {
  @extend .alert;

  color: var(--fs-color-primary-500);
  background-color: var(--fs-color-primary-200);
  border-color: var(--fs-color-primary-300);

  display: flex;
  align-items: center;
}

.introfield__title {
  margin-top: 0;
  margin-bottom: .25rem;
}

.introfield__description {
  margin-bottom: 0;
  @media (max-width: 576px) {
    display: none;
  }
}

.errorfield__link {
  @extend .btn;
  @extend .btn-sm;
  @extend .btn-danger;

  font-weight: 600;

  &:not(:last-child) {
    margin-right: .5rem;
  }
}
</style>

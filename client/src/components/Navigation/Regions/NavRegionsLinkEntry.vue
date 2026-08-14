<template>
  <div class="rounded-bottom border pb-2">
    <template v-for="(menu, key) in menuEntries">
      <router-link
        v-if="menu.href"
        :key="`link-${key}`"
        :to="formatLink(menu)"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas" :class="menu.icon" />
        {{ $t(menu.text) }}
      </router-link>
      <!-- entries without a target only run a function, e.g. opening the conference popup -->
      <a
        v-else
        :key="`action-${key}`"
        role="menuitem"
        class="dropdown-item dropdown-action pointer-always"
        @click="menu.func()"
      >
        <i class="icon-subnav fas" :class="menu.icon" />
        {{ $t(menu.text) }}
      </a>
    </template>
  </div>
</template>

<script>
import ConferenceOpener from '@/mixins/ConferenceOpenerMixin'
import { useUserStore } from '@/stores/user'
import { REGION_UNIT_TYPE } from '@/stores/regions'

export default {
  name: 'NavRegionsLinkEntry',
  mixins: [ConferenceOpener],
  props: {
    entry: {
      type: Object,
      default: () => {},
    },
  },
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  computed: {
    showStatisticsAndMembers () {
      /* Statistics and members page are temporarily disabled because they are too inefficient for Europe and large
       countries. Region type "Country" is also used for Europe. */
      return ![REGION_UNIT_TYPE.COUNTRY, REGION_UNIT_TYPE.CONTINENT].includes(this.entry.type)
    },
    menuEntries () {
      /* Every entry links to its href, except for the ones that only have a func to call. */
      const menu = [
        {
          href: 'publicRegion', icon: 'fa-door-open', text: 'menu.entry.public',
        },
        {
          href: 'forum', icon: 'fa-comment-alt', text: 'menu.entry.forum',
        },
        {
          href: 'stores', icon: 'fa-cart-plus', text: 'menu.entry.stores',
        },
        {
          href: 'workingGroups', icon: 'fa-users', text: 'terminology.groups',
        },
        {
          href: 'events', icon: 'fa-calendar-alt', text: 'menu.entry.events',
        },
        {
          href: 'foodsharepoints', icon: 'fa-recycle', text: 'terminology.fsp',
        },
        {
          href: 'polls', icon: 'fa-poll-h', text: 'terminology.polls',
        },
      ]

      if (this.showStatisticsAndMembers) {
        menu.push({
          href: 'members', icon: 'fa-user', text: 'menu.entry.members',
        })
      }

      if (this.entry.hasResources) {
        menu.push({
          href: 'resources', icon: 'fa-shapes', text: 'resource_mosaic.title',
        })
      }

      menu.push({
        href: 'options', icon: 'fa-tools', text: 'menu.entry.options',
      })

      if (this.entry.hasAchievements) {
        menu.push({
          href: 'achievements', icon: 'fa-tags', text: 'terminology.achievements',
        })
      }

      if (this.showStatisticsAndMembers) {
        menu.push({
          href: 'statistic', icon: 'fa-chart-bar', text: 'terminology.statistic',
        })
      }

      if (this.entry.hasConference) {
        menu.push({
          icon: 'fa-users', text: 'menu.entry.conference', func: () => this.showConferencePopup(this.entry.id),
        })
      }

      if (this.entry.mayAccessReports) {
        const viewer = this.entry.isReportAdmin ? 'report' : this.entry.isArbitrationAdmin ? 'arbitration' : 'orga'
        menu.push({
          href: 'reports', icon: 'fa-people-arrows', text: `terminology.reports.${viewer}`,
        })
      }

      if (this.entry.mailboxId > 0) {
        menu.push({
          href: 'mailbox', icon: 'fa-fas fa-envelope', text: 'menu.entry.mailbox', linkId: this.entry.mailboxId,
        })
      }

      if (this.entry.isAdmin || this.userStore.isOrga) {
        menu.push({
          href: 'forum', special: 1, icon: 'fa-comment-dots', text: 'menu.entry.BOTforum',
        })
      }

      return menu
    },
  },
  methods: {
    formatLink (menu) {
      const id = menu.linkId ?? this.entry.id
      return this.$url(menu.href, id, menu.special)
    },
  },
}
</script>

<style lang="scss" scoped>
.pointer-always {
  cursor: pointer;
}
</style>

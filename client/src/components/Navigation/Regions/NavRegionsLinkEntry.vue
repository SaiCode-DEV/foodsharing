<template>
  <div class="rounded-bottom border pb-2">
    <a
      v-for="(menu,key) in menuEntries"
      :key="key"
      :href="formatLink(menu)"
      role="menuitem"
      class="dropdown-item dropdown-action pointer-always"
      @click="onClick(menu, $event)"
    >
      <i class="icon-subnav fas" :class="menu.icon" />
      {{ $t(menu.text) }}
    </a>
  </div>
</template>

<script>
import ConferenceOpener from '@/mixins/ConferenceOpenerMixin'
import { useUserStore } from '@/stores/user'
import { REGION_UNIT_TYPE, SUB_PAGE } from '@/stores/regions'

export default {
  name: 'NavRegionsLinkEntry',
  mixins: [ConferenceOpener],
  props: {
    entry: {
      type: Object,
      default: () => {},
    },
    /**
     * If true, links to sub-pages will be actual links that reload the page. If false, links will make this component
     * emit a 'change-page' event.
     */
    isLinkingSubpages: { type: Boolean, required: true },
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
      return this.entry.type !== REGION_UNIT_TYPE.COUNTRY
    },
    menuEntries () {
      /* An entry that has a subPage property emits a "change-page" event if this is a dropdown menu. This makes the
      region page change the Vue component without a reload. Otherwise the entry is a link to the href property. */
      const menu = [
        {
          href: 'publicRegion', icon: 'fa-door-open', text: 'menu.entry.public',
        },
        {
          href: 'forum', icon: 'fa-comments', text: 'menu.entry.forum', subPage: SUB_PAGE.FORUM,
        },
        {
          href: 'stores', icon: 'fa-cart-plus', text: 'menu.entry.stores',
        },
        {
          href: 'workingGroups', icon: 'fa-users', text: 'terminology.groups',
        },
        {
          href: 'events', icon: 'fa-calendar-alt', text: 'menu.entry.events', subPage: SUB_PAGE.EVENTS,
        },
        {
          href: 'foodsharepoints', icon: 'fa-recycle', text: 'terminology.fsp', subPage: SUB_PAGE.FOODSHARINGPOINT,
        },
        {
          href: 'polls', icon: 'fa-poll-h', text: 'terminology.polls', subPage: SUB_PAGE.POLLS,
        },
      ]

      if (this.showStatisticsAndMembers) {
        menu.push({
          href: 'members', icon: 'fa-user', text: 'menu.entry.members', subPage: SUB_PAGE.MEMBERS,
        })
      }

      if (this.entry.hasResources) {
        menu.push({
          href: 'resources', icon: 'fa-shapes', text: 'resource_mosaic.title', subPage: SUB_PAGE.RESOURCES,
        })
      }

      menu.push({
        href: 'options', icon: 'fa-tools', text: 'menu.entry.options', subPage: SUB_PAGE.OPTIONS,
      })

      if (this.entry.hasAchievements) {
        menu.push({
          href: 'achievements', icon: 'fa-tags', text: 'terminology.achievements', subPage: SUB_PAGE.ACHIEVEMENTS,
        })
      }

      if (this.showStatisticsAndMembers) {
        menu.push({
          href: 'statistic', icon: 'fa-chart-bar', text: 'terminology.statistic', subPage: SUB_PAGE.STATISTIC,
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
          href: 'forum', special: 1, icon: 'fa-comment-dots', text: 'menu.entry.BOTforum', subPage: SUB_PAGE.AMBASSADOR_FORUM,
        })
      }

      return menu
    },
  },
  methods: {
    formatLink (menu) {
      const id = menu.linkId ?? this.entry.id
      // If the entry has no href, we do not want any default link behavior
      return menu.href ? this.$url(menu.href, id, menu.special) : undefined
    },
    onClick (menu, event) {
      if (menu.func) {
        menu.func()
      } else if (menu.subPage && !this.isLinkingSubpages) {
        // If isLinkingSubpages is false, handle via Vue and prevent the default link behavior
        this.$emit('change-page', menu.subPage)
        event.preventDefault()
      }
      // Else: If isLinkingSubpages is true, the link has an href attribute and the page will reload
    },
  },
}
</script>

<style lang="scss" scoped>
.pointer-always {
  cursor: pointer;
}
</style>

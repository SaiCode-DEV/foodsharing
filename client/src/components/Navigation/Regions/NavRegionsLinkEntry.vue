<template>
  <div>
    <a
      v-for="(menu,key) in menuEntries"
      :key="key"
      :href="formatLink(menu)"
      role="menuitem"
      class="dropdown-item dropdown-action"
      @click="menu.func ? menu.func() : null"
    >
      <i class="icon-subnav fas" :class="menu.icon" />
      {{ $i18n(menu.text) }}
    </a>
  </div>
</template>

<script>
import ConferenceOpener from '@/mixins/ConferenceOpenerMixin'
import DataUser from '@/stores/user'

export default {
  name: 'NavRegionsLinkEntry',
  mixins: [ConferenceOpener],
  props: {
    entry: {
      type: Object,
      default: () => {},
    },
  },
  computed: {
    showStatisticsAndMembers () {
      /* Statistics and members page are temporarily disabled because they are too inefficient for Europe and large
       countries. Region type 6 is "Country" which is also used for Europe. */
      return this.entry.type !== 6
    },
    menuEntries () {
      const menu = [
        {
          href: 'forum', icon: 'fa-comments', text: 'menu.entry.forum',
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

      menu.push({
        href: 'options', icon: 'fa-tools', text: 'menu.entry.options',
      })

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

      if (this.entry.maySetRegionPin) {
        menu.push({
          href: 'pin', icon: 'fa-users', text: 'menu.entry.pin',
        })
      }
      if (this.entry.mayAccessReportGroupReports) {
        menu.push({
          href: 'reports', icon: 'fa-poo', text: 'terminology.reports',
        })
      }
      if (this.entry.mayAccessArbitrationGroupReports) {
        menu.push({
          href: 'reports', icon: 'fa-poo', text: 'terminology.arbitration',
        })
      }

      if (this.entry.mailboxId > 0) {
        menu.push({
          href: 'mailbox', icon: 'fa-fas fa-envelope', text: 'menu.entry.mailbox', linkId: this.entry.mailboxId,
        })
      }

      if (this.entry.isAdmin || DataUser.getters.isOrga) {
        menu.push({
          href: 'forum', special: 1, icon: 'fa-comment-dots', text: 'menu.entry.BOTforum',
        })
        menu.push({
          href: 'passports', icon: 'fa-address-card', text: 'menu.entry.ids',
        })
      }

      return menu
    },
  },
  methods: {
    formatLink (menu) {
      const id = menu.linkId ?? this.entry.id
      return menu.href ? this.$url(menu.href, id, menu.special) : '#'
    },
  },
}
</script>

<template>
  <div>
    <a
      v-for="(menu,key) in menuEntries"
      :key="key"
      :href="menu.href ? $url(menu.href, entry.id, menu.special) : '#'"
      role="menuitem"
      class="dropdown-item dropdown-action"
      @click="menu.func ? menu.func() : null"
    >
      <i class="icon-subnav fas" :class="menu.icon" />
      {{ menu.text }}
    </a>
  </div>
</template>

<script>
import ConferenceOpener from '@/mixins/ConferenceOpenerMixin'

export default {
  name: 'NavGroupsLinkEntry',
  mixins: [ConferenceOpener],
  props: {
    entry: {
      type: Object,
      default: () => {},
    },
    applicationCount: { type: Number, default: 0 },
  },
  computed: {
    menuEntries () {
      const menu = [
        {
          href: 'wall', icon: 'fa-bullhorn', text: this.$i18n('menu.entry.wall'),
        },
        {
          href: 'forum', icon: 'fa-comment-alt', text: this.$i18n('menu.entry.forum'),
        },
        {
          href: 'events', icon: 'fa-calendar-alt', text: this.$i18n('menu.entry.events'),
        },
        {
          href: 'polls', icon: 'fa-poll-h', text: this.$i18n('terminology.polls'),
        },
        {
          href: 'members', icon: 'fa-user', text: this.$i18n('menu.entry.members'),
        },
      ]

      if (this.entry.hasSubgroups) {
        menu.push({
          href: 'subGroups', icon: 'fa-user-friends', text: this.$i18n('terminology.subgroups'),
        })
      }

      if (this.entry.hasConference) {
        menu.push({
          icon: 'fa-users', text: this.$i18n('menu.entry.conference'), func: () => this.showConferencePopup(this.entry.id),
        })
      }

      if (this.entry.isAdmin) {
        menu.push({
          href: 'workingGroupEdit', icon: 'fa-cog', text: this.$i18n('menu.entry.workingGroupEdit'),
        })
      }

      if (this.applicationCount > 0) {
        menu.push({
          href: 'applications', icon: 'fa-cog', text: this.$i18n('menu.entry.applications', { count: this.applicationCount }),
        })
      }

      if (this.entry.mailboxId > 0) {
        menu.push({
          href: 'mailbox', icon: 'fa-fas fa-envelope', text: this.$i18n('menu.entry.mailbox'),
        })
      }

      if (this.entry.isChainGroup) {
        menu.push({
          href: 'chains', icon: 'fa-link', text: this.$i18n('menu.entry.chainList'),
        })
      }

      return menu
    },
  },
}
</script>

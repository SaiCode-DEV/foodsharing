<template>
  <div>
    <template v-for="(menu, key) in menuEntries">
      <router-link
        v-if="menu.href"
        :key="`link-${key}`"
        :to="formatLink(menu)"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas" :class="menu.icon" />
        {{ menu.text }}
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
        {{ menu.text }}
      </a>
    </template>
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
      /* Every entry links to its href, except for the ones that only have a func to call. */
      const menu = [
        {
          href: 'wall', icon: 'fa-bullhorn', text: this.$t('menu.entry.wall'),
        },
        {
          href: 'forum', icon: 'fa-comment-alt', text: this.$t('menu.entry.forum'),
        },
        {
          href: 'events', icon: 'fa-calendar-alt', text: this.$t('menu.entry.events'),
        },
        {
          href: 'polls', icon: 'fa-poll-h', text: this.$t('terminology.polls'),
        },
        {
          href: 'members', icon: 'fa-user', text: this.$t('menu.entry.members'),
        },
      ]

      if (this.entry.hasResources) {
        menu.push({
          href: 'resources', icon: 'fa-shapes', text: this.$t('resource_mosaic.title'),
        })
      }

      if (this.entry.hasSubgroups) {
        menu.push({
          href: 'workingGroups', icon: 'fa-user-friends', text: this.$t('terminology.subgroups'),
        })
      }

      if (this.entry.hasConference) {
        menu.push({
          icon: 'fa-users', text: this.$t('menu.entry.conference'), func: () => this.showConferencePopup(this.entry.id),
        })
      }

      if (this.entry.isAdmin) {
        menu.push({
          href: 'workingGroupEdit', icon: 'fa-cog', text: this.$t('menu.entry.workingGroupEdit'),
        })
      }

      if (this.entry.hasAchievements) {
        menu.push({
          href: 'achievements', icon: 'fa-tags', text: this.$t('terminology.achievements'),
        })
      }

      if (this.applicationCount > 0) {
        menu.push({
          href: 'applications', icon: 'fa-cog', text: this.$t('menu.entry.applications', { count: this.applicationCount }),
        })
      }

      if (this.entry.mailboxId > 0) {
        menu.push({
          href: 'mailbox', icon: 'fa-fas fa-envelope', text: this.$t('menu.entry.mailbox'), linkId: this.entry.mailboxId,
        })
      }

      if (this.entry.isChainGroup) {
        menu.push({
          href: 'chains', icon: 'fa-link', text: this.$t('menu.entry.chainList'),
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

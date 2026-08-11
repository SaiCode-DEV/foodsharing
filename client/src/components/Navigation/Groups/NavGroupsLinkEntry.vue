<template>
  <div>
    <router-link
      v-for="(menu,key) in menuEntries"
      :key="key"
      v-slot="{ navigate }"
      :to="formatLink(menu) || '/'"
      custom
    >
      <a
        :href="formatLink(menu)"
        role="menuitem"
        class="dropdown-item dropdown-action pointer-always"
        @click="(e) => { onClick(menu, e); if (formatLink(menu)) navigate(e); }"
      >
        <i class="icon-subnav fas" :class="menu.icon" />
        {{ menu.text }}
      </a>
    </router-link>
  </div>
</template>

<script>
import ConferenceOpener from '@/mixins/ConferenceOpenerMixin'
import { SUB_PAGE } from '@/stores/regions'

export default {
  name: 'NavGroupsLinkEntry',
  mixins: [ConferenceOpener],
  props: {
    entry: {
      type: Object,
      default: () => {},
    },
    applicationCount: { type: Number, default: 0 },
    /**
     * If true, links to sub-pages will be actual links that reload the page. If false, links will make this component
     * emit a 'change-page' event.
     */
    isLinkingSubpages: { type: Boolean, required: true },
  },
  computed: {
    menuEntries () {
      /* An entry that has a subPage property emits a "change-page" event if this is a dropdown menu. This makes the
      region page change the Vue component without a reload. Otherwise the entry is a link to the href property. */
      const menu = [
        {
          href: 'wall', icon: 'fa-bullhorn', text: this.$t('menu.entry.wall'), subPage: SUB_PAGE.WALL,
        },
        {
          href: 'forum', icon: 'fa-comment-alt', text: this.$t('menu.entry.forum'), subPage: SUB_PAGE.FORUM,
        },
        {
          href: 'events', icon: 'fa-calendar-alt', text: this.$t('menu.entry.events'), subPage: SUB_PAGE.EVENTS,
        },
        {
          href: 'polls', icon: 'fa-poll-h', text: this.$t('terminology.polls'), subPage: SUB_PAGE.POLLS,
        },
        {
          href: 'members', icon: 'fa-user', text: this.$t('menu.entry.members'), subPage: SUB_PAGE.MEMBERS,
        },
      ]

      if (this.entry.hasResources) {
        menu.push({
          href: 'resources', icon: 'fa-shapes', text: this.$t('resource_mosaic.title'), subPage: SUB_PAGE.RESOURCES,
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
          href: 'workingGroupEdit', icon: 'fa-cog', text: this.$t('menu.entry.workingGroupEdit'), subPage: SUB_PAGE.SETTINGS,
        })
      }

      if (this.entry.hasAchievements) {
        menu.push({
          href: 'achievements', icon: 'fa-tags', text: this.$t('terminology.achievements'), subPage: SUB_PAGE.ACHIEVEMENTS,
        })
      }

      if (this.applicationCount > 0) {
        menu.push({
          href: 'applications', icon: 'fa-cog', text: this.$t('menu.entry.applications', { count: this.applicationCount }), subPage: SUB_PAGE.APPLICATIONS,
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

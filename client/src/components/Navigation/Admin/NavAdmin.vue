<template>
  <Dropdown
    v-if="hasAdminPermissions"
    :title="$t('navigation.system_administration')"
    icon="fa-gear"
    is-fixed-size
    is-scrollable
  >
    <template #content>
      <FsLink
        v-if="permissions.administrateBlog"
        :to="$url('blogList')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-newspaper" /> {{ $t('system_administration.blog') }}
      </FsLink>
      <FsLink
        v-if="permissions.editQuiz"
        :to="$url('quiz_admin_edit')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-question-circle" /> {{ $t('system_administration.quiz') }}
      </FsLink>
      <FsLink
        v-if="permissions.administrateRegions"
        :to="$url('regionAdmin')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-map" /> {{ $t('system_administration.regions') }}
      </FsLink>
      <FsLink
        v-if="permissions.mayAdministrateOAuthClients"
        :to="$url('oauthClientsAdmin')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-key" /> {{ $t('oauth.admin.title') }}
      </FsLink>
      <FsLink
        v-if="permissions.mayAdministrateEmailBlocklist"
        :to="$url('emailBlocklistAdmin')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-ban" /> {{ $t('email_blocklist.admin.title') }}
      </FsLink>
      <FsLink
        v-if="permissions.editContent"
        :to="$url('contentEdit')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-file-alt" /> {{ $t('system_administration.content') }}
      </FsLink>
      <FsLink
        v-if="permissions.editStoreCategories"
        :to="$url('editCategories', 'store')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-store" /> {{ $t('system_administration.store_categories') }}
      </FsLink>
      <FsLink
        v-if="permissions.editResourceCategories"
        :to="$url('editCategories', 'resource')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-shapes" /> {{ $t('system_administration.resource_categories') }}
      </FsLink>
      <FsLink
        v-if="permissions.editAchievements"
        :to="$url('editAchievements')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-tags" /> {{ $t('achievements.editTitle') }}
      </FsLink>
      <FsLink
        v-if="permissions.editDonationPage"
        :to="$url('donationAdminPage')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-hand-holding-heart" /> {{ $t('system_administration.donation_page_edit') }}
      </FsLink>
    </template>
  </Dropdown>
</template>
<script>
// Stores
import { useUserStore } from '@/stores/user'
// Components
import Dropdown from '../_NavItems/NavDropdown'
import FsLink from '@/components/UI/FsLink.vue'
// Mixins
import RouteCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'

export default {
  components: {
    Dropdown,
    FsLink,
  },
  mixins: [RouteCheckMixin],
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  computed: {
    permissions () {
      return this.userStore.getPermissions
    },
    hasAdminPermissions () {
      return this.userStore.hasAdminPermissions
    },
  },
}
</script>

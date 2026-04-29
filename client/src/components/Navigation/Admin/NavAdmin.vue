<template>
  <Dropdown
    v-if="hasAdminPermissions"
    :title="$t('navigation.system_administration')"
    icon="fa-gear"
    is-fixed-size
    is-scrollable
  >
    <template #content>
      <a
        v-if="permissions.administrateBlog"
        :href="$url('blogList')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-newspaper" /> {{ $t('system_administration.blog') }}
      </a>
      <a
        v-if="permissions.editQuiz"
        :href="$url('quiz_admin_edit')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-question-circle" /> {{ $t('system_administration.quiz') }}
      </a>
      <a
        v-if="permissions.administrateRegions"
        :href="$url('regionAdmin')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-map" /> {{ $t('system_administration.regions') }}
      </a>
      <a
        v-if="permissions.mayAdministrateOAuthClients"
        :href="$url('oauthClientsAdmin')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-key" /> {{ $t('oauth.admin.title') }}
      </a>
      <a
        v-if="permissions.editContent"
        :href="$url('contentEdit')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-file-alt" /> {{ $t('system_administration.content') }}
      </a>
      <a
        v-if="permissions.editStoreCategories"
        :href="$url('editCategories', 'store')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-store" /> {{ $t('system_administration.store_categories') }}
      </a>
      <a
        v-if="permissions.editResourceCategories"
        :href="$url('editCategories', 'resource')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-shapes" /> {{ $t('system_administration.resource_categories') }}
      </a>
      <a
        v-if="permissions.editAchievements"
        :href="$url('editAchievements')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-tags" /> {{ $t('achievements.editTitle') }}
      </a>
    </template>
  </Dropdown>
</template>
<script>
// Stores
import { useUserStore } from '@/stores/user'
// Components
import Dropdown from '../_NavItems/NavDropdown'
// Mixins
import RouteCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'

export default {
  components: {
    Dropdown,
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

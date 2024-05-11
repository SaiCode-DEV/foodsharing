<template>
  <Dropdown
    v-if="hasAdminPermissions"
    :title="$i18n('navigation.system_administration')"
    class="is-open-on-mobile"
    direction="right"
  >
    <template #content>
      <a
        v-if="permissions.administrateBlog"
        :href="$url('blogList')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-newspaper" /> {{ $i18n('system_administration.blog') }}
      </a>
      <a
        v-if="permissions.editQuiz"
        :href="$url('quiz_admin_edit')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-question-circle" /> {{ $i18n('system_administration.quiz') }}
      </a>
      <a
        v-if="permissions.administrateRegions"
        :href="$url('regionAdmin')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-map" /> {{ $i18n('system_administration.regions') }}
      </a>
      <!--
      <a
        v-if="permissions.administrateNewsletterEmail"
        :href="$url('email')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-envelope" /> {{ $i18n('system_administration.email') }}
      </a>
      -->
      <a
        v-if="permissions.manageMailboxes"
        :href="$url('mailboxManage')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-envelope" /> {{ $i18n('system_administration.mailboxes') }}
      </a>
      <a
        v-if="permissions.editContent"
        :href="$url('contentEdit')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-file-alt" /> {{ $i18n('system_administration.content') }}
      </a>
    </template>
  </Dropdown>
</template>
<script>
// Stores
import DataUser from '@/stores/user'
// Components
import Dropdown from '../_NavItems/NavDropdown'
// Mixins
import RouteCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'

export default {
  components: {
    Dropdown,
  },
  mixins: [RouteCheckMixin],
  computed: {
    permissions () {
      return DataUser.getters.getPermissions()
    },
    hasAdminPermissions () {
      return DataUser.getters.hasAdminPermissions()
    },
  },
}
</script>

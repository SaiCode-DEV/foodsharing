<template>
  <Dropdown
    :title="$t('login.topbar')"
    direction="right"
    is-fixed-size
    class="testing-login-dropdown is-open-on-mobile"
  >
    <template #content>
      <LoginForm />
    </template>
    <template #actions>
      <router-link
        :to="$url('passwordReset')"
        role="menuitem"
        class="testing-login-click-password-reset dropdown-item dropdown-action"
      >
        {{ $t('login.forgotten_password_label') }}
      </router-link>
      <router-link
        :to="$url('joininfo')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        {{ $t('register.topbar') }}
      </router-link>
    </template>
  </Dropdown>
</template>
<script>
// Components
import Dropdown from '../_NavItems/NavDropdown'
import LoginForm from '../../Login/LoginForm.vue'

export default {
  name: 'MenuStores',
  components: { Dropdown, LoginForm },
}
</script>
<style lang="scss" scoped>
@import '../../../scss/icon-sizes.scss';
</style>

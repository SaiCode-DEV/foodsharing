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
      <a
        :href="$url('passwordReset')"
        role="menuitem"
        class="testing-login-click-password-reset dropdown-item dropdown-action"
        v-text="$t('login.forgotten_password_label')"
      />
      <a
        :href="$url('joininfo')"
        role="menuitem"
        class="dropdown-item dropdown-action"
        v-text="$t('register.topbar')"
      />
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

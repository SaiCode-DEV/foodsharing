<template>
  <div class="navbar-navside">
    <MetaNavLoggedOut v-if="viewIsMobile" />
    <ul class="sidenav">
      <li class="nav-item">
        <ThemeSwitcher />
      </li>
      <NavLink
        :to="$url('joininfo')"
        :title="$t('register.topbar')"
      />
      <NavLogin />
    </ul>
  </div>
</template>

<script>
// Store
import MetaNavData from '../../Data/MetaNavData.json'
//
import NavLink from '@/components/Navigation/_NavItems/NavLink'
import NavLogin from '@/components/Navigation/Login/NavLogin.vue'
// State
import MetaNavLoggedOut from '../MetaNav/LoggedOut.vue'
// Mixins
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import ThemeSwitcher from '@/components/ThemeSwitcher.vue'

export default {
  components: {
    NavLink,
    NavLogin,
    MetaNavLoggedOut,
    ThemeSwitcher,
  },
  mixins: [MediaQueryMixin],
  data () {
    return {
      metaNav: MetaNavData,
    }
  },
}
</script>

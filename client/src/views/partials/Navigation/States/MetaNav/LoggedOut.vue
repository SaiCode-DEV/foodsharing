<template>
  <ul class="metanav">
    <Logo v-if="!viewIsMobile" />
    <div v-if="viewIsMobile" class="metanav">
      <NavItem
        v-for="entry of MainNavData"
        :key="entry.title"
        :entry="entry"
      />
    </div>
    <NavItem
      v-for="(entry, idx) of metaNav"
      :key="idx"
      :entry="entry"
    />
  </ul>
</template>

<script>
// Data
import MetaNavData from '../../Data/MetaNavData.json'
import MainNavData from '../../Data/MainNavData.json'
//
import Logo from '@/components/Navigation/Logo'
import NavItem from '@/components/Navigation/_NavItems/NavItem'
//
// Mixins
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: {
    Logo,
    NavItem,
  },
  mixins: [MediaQueryMixin],
  data () {
    return {
      metaNav: MetaNavData.filter(m => !m.isInternal),
      MainNavData,
    }
  },
}
</script>

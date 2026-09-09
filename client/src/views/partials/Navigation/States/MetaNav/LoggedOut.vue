<template>
  <ul class="metanav">
    <Logo v-if="!viewIsMobile" />
    <div v-if="viewIsMobile" class="metanav">
      <NavItem
        v-for="entry of mainNavFiltered"
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
import MetaNavData from '../../Data/MetaNavData.json'
import MainNavData from '../../Data/MainNavData.json'
import Logo from '@/components/Navigation/Logo'
import NavItem from '@/components/Navigation/_NavItems/NavItem'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import { useNavFilter } from '@/composables/useNavFilter'
import { computed } from 'vue'

export default {
  components: {
    Logo,
    NavItem,
  },
  mixins: [MediaQueryMixin],
  setup () {
    const { filterNavData } = useNavFilter()
    const mainNavFiltered = computed(() => filterNavData(MainNavData))

    return {
      mainNavFiltered,
    }
  },
  data () {
    return {
      metaNav: MetaNavData.filter(m => !m.isInternal),
    }
  },
}
</script>

import '@/core'
import '@/globals'
import '@/tablesorter'
import 'jquery.tinymce'
import './Quiz.css'
import { expose } from '@/utils'
import { ifconfirm } from '@/script'
import { GET } from '@/browser'
import Wall from '@/components/Wall/Wall'
import { vueApply, vueRegister } from '@/vue'

const sub = GET('sub')
if (sub === 'wall') {
  vueRegister({ Wall })
  vueApply('#vue-wall', true)
}

expose({
  ifconfirm,
})

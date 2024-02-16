/* eslint-disable eqeqeq */
import '@/core'
import '@/globals'
import './Region.css'
import { vueRegister, vueApply } from '@/vue'
import RegionPage from './components/RegionPage'

vueRegister({ RegionPage })
vueApply('#region-page')

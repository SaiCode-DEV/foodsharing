import { ref, onMounted, onUnmounted, computed } from 'vue'

export const breakpoints = ['sm', 'md', 'lg', 'xl', 'xxl']

const thresholds = {
  xs: 0,
  sm: 576,
  md: 768,
  lg: 992,
  xl: 1200,
  xxl: 1400,
}

export function useMediaQuery () {
  const width = ref(0)
  const height = ref(0)

  const updateSize = () => {
    width.value = window.innerWidth
    height.value = window.innerHeight
  }

  onMounted(() => {
    updateSize()
    window.addEventListener('resize', updateSize)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', updateSize)
  })

  // Breakpoint checks
  const xs = computed(() => width.value < thresholds.sm)
  const sm = computed(() => width.value >= thresholds.sm && width.value < thresholds.md)
  const md = computed(() => width.value >= thresholds.md && width.value < thresholds.lg)
  const lg = computed(() => width.value >= thresholds.lg && width.value < thresholds.xl)
  const xl = computed(() => width.value >= thresholds.xl && width.value < thresholds.xxl)
  const xxl = computed(() => width.value >= thresholds.xxl)

  // Combined breakpoint checks
  const smAndUp = computed(() => width.value >= thresholds.sm)
  const mdAndUp = computed(() => width.value >= thresholds.md)
  const lgAndUp = computed(() => width.value >= thresholds.lg)
  const xlAndUp = computed(() => width.value >= thresholds.xl)

  const smAndDown = computed(() => width.value < thresholds.md)
  const mdAndDown = computed(() => width.value < thresholds.lg)
  const lgAndDown = computed(() => width.value < thresholds.xl)
  const xlAndDown = computed(() => width.value < thresholds.xxl)

  const name = computed(() => {
    if (xs.value) return 'xs'
    if (sm.value) return 'sm'
    if (md.value) return 'md'
    if (lg.value) return 'lg'
    if (xl.value) return 'xl'
    return 'xxl'
  })

  const platform = computed(() => ({
    touch: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
  }))

  const mobile = computed(() => xs.value || sm.value)

  return {
    width,
    height,
    xs,
    sm,
    md,
    lg,
    xl,
    xxl,
    smAndUp,
    mdAndUp,
    lgAndUp,
    xlAndUp,
    smAndDown,
    mdAndDown,
    lgAndDown,
    xlAndDown,
    name,
    mobile,
    platform,
    thresholds,
  }
}

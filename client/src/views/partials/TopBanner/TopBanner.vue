<template>
  <div
    ref="banner"
    :class="`top-banner pb-3 pt-1 list-group-item list-group-item-${variant}`"
  >
    <button
      :class="`visibility-toggle list-group-item list-group-item-action list-group-item-${variant} p-0 text-center`"
      @click="toggleExpanded()"
    >
      <i class="fas fa-chevron-down" />
    </button>
    <i class="fas fa-times top-banner-close" @click="toggleExpanded(false)" />

    <div class="container d-block">
      <slot />
    </div>
  </div>
</template>

<script>

export default {
  props: {
    variant: { type: String, default: 'success' },
    tag: { type: String, default: 'topBanner' },
    expandIntervalDays: { type: Number, default: null },
  },
  data () {
    return {
      isExpanded: false,
      isAnimationRunning: false,
    }
  },
  async mounted () {
    window.addEventListener('resize', this.resizeHandler)
    window.addEventListener('load', this.resizeHandler)
    document.querySelector('nav.navigation').classList.add('top-banner')
    this.resizeHandler()

    // adjust navbar-height
    await this.$nextTick()
    const height = document.querySelector('nav.navigation').getBoundingClientRect().height + 'px'
    document.documentElement.style.setProperty('--navbar-height', height)

    this.autoExpandAfterInterval()
  },
  methods: {
    async toggleExpanded (show = null) {
      this.isExpanded = (show === null) ? !this.isExpanded : show
      document.documentElement.classList[this.isExpanded ? 'add' : 'remove']('top-banner-isExpanded')
      this.$emit(this.isExpanded ? 'show' : 'hide')
      this.isAnimationRunning = true
      await new Promise(resolve => window.setTimeout(resolve, 500))
      this.isAnimationRunning = true
      this.$emit(this.isExpanded ? 'shown' : 'hidden')
    },
    resizeHandler () {
      document.documentElement.style.setProperty('--top-banner-height', this.$refs.banner.getBoundingClientRect().height + 'px')
    },
    async autoExpandAfterInterval () {
      if (!this.expandIntervalDays) return

      // Wait a few seconds for the user to be ready to really use this page.
      // This way, the banner doesn't open on pages the user only uses to navigate
      await new Promise(resolve => window.setTimeout(resolve, 10_000))

      const storageKey = `banner-${this.tag}-last-opened`
      const lastOpened = localStorage.getItem(storageKey)
      const now = new Date()
      const millisecondsPerDay = 86400000
      if (lastOpened > now - this.expandIntervalDays * millisecondsPerDay) return

      this.toggleExpanded(true)

      // Wait a few seconds before saving that the banner was shown, to make sure
      // it was actually seen and didn't open right before the user navigated away
      await new Promise(resolve => window.setTimeout(resolve, 5_000))
      localStorage.setItem(storageKey, +now)
    },
  },
}
</script>

<style lang="scss" scoped>
.top-banner {
  position: absolute !important;
  left: 0;
  right: 0;
  transform: translateY(calc(-100% - 15px));
}
.visibility-toggle {
  width: 80px;
  height: 20px;
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  top: 100%;
  border-radius: 0 0 var(--border-radius) var(--border-radius);
  > i {
    transition: transform 0.25s ease;
  }
}
.top-banner-isExpanded .visibility-toggle>i {
  transform: rotate(180deg);
}

.top-banner-close {
  position: absolute;
  top: 0.25em;
  right: 0.5em;
  font-size: 1.75em;
  width: 1em;
  height: 1em;
  text-align: center;
  opacity: 0.5;
  cursor: pointer;
  &:hover {
    opacity: 1;
  }
}
</style>
<style lang="scss">
:root {
  --top-banner-height: 150px;
}
.navigation.top-banner {
  padding-top: 15px;
}
body {
  position: relative;
}
.navigation, body {
  transition: top 0.5s cubic-bezier(.22,.61,.36,1);
  top: 0px;
}
.top-banner-isExpanded .navigation, .top-banner-isExpanded body {
  top: var(--top-banner-height);
}
</style>

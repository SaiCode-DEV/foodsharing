<template>
  <b-avatar
    v-b-tooltip.ds300.noninteractive="computedTooltip"
    :src="imageSrc"
    :size="size"
    :href="computedHref"
    :variant="computedVariant"
    :badge-variant="badgeVariant"
    badge-bottom
    :class="`unobtrusive-link avatar-${variant} ${isSleeping ? 'sleep' : ''} ${transparent ? 'avatar-transparent' : ''}`"
    :[shape]="true"
    v-bind="options"
    @click="$emit('click', $event)"
  >
    <i
      v-if="icon"
      class="avatar-icon"
      :class="icon"
    />

    <template v-if="$slots.badge" #badge>
      <slot name="badge" />
    </template>
  </b-avatar>
</template>
<script>
import { lazyLoad } from './visibilityObserver'

export default {
  props: {
    icon: { type: String, default: '' },
    image: { type: String, default: '' },
    size: { type: Number, default: 35 },
    badgeVariant: { type: String, default: 'secondary' },
    badgeSize: { type: String, default: '75%' },
    variant: { type: [String, undefined], default: undefined },
    tooltip: { type: [String, undefined], default: undefined },
    transparent: { type: Boolean, default: false },

    // Allowed values: 'square', 'rounded', 'round'
    shape: { type: String, default: 'rounded' },

    // Object with id, name, avatar and (optionally) isSleeping properties.
    user: { type: Object, default: null },

    // Uses the given string as href. If undefined is provided, a link to the given user profile is used.
    // Disabling the profile link is possible by passing ''
    href: { type: [String, undefined], default: undefined },

    // Further options provided to b-avatar (TODO remove if unused)
    options: { type: Object, default: () => ({}) },
  },
  data () {
    return {
      lazyLoaded: false,
      observer: null,
    }
  },
  computed: {
    imageSrc () {
      const prefix = [
        [35, 'mini_q_'],
        [50, '50_q_'],
        [Infinity, '130_q_'],
      ].find(x => x[0] >= this.size)[1]
      const image = this.image || this.user?.avatar

      if (!this.lazyLoaded || !image) {
        return '/img/' + prefix + 'avatar.png'
      }

      if (image?.startsWith('/api/uploads/')) {
        return image + `?w=${Math.ceil(this.size)}&h=${Math.ceil(this.size)}` // path for pictures uploaded with the new API
      }
      if (image && !image?.endsWith('avatar.png')) {
        return '/images/' + prefix + image // backward compatible path for old pictures
      }
      return '/img/' + prefix + 'avatar.png'
    },
    computedHref () {
      if (typeof this.href === 'string') {
        return this.href
      }
      if (this.user?.id) {
        return this.$url('profile', this.user.id)
      }
      return ''
    },
    computedTooltip () {
      if (typeof this.tooltip === 'string') {
        return this.tooltip
      }
      if (this.user) {
        return this.user?.name
      }
      return ''
    },
    computedVariant () {
      if (this.imageSrc.endsWith('avatar.png')) return 'light'
      if (this.imageSrc) return ''
      return this.variant
    },
    isSleeping () {
      if (!this.user) return false
      return this.user.isSleeping ?? this.user.sleep_status
    },
    sleepImageSrc () {
      if (!this.isSleeping) return ''
      const size = [35, 50].find(x => x >= this.size) ?? 130
      return `url('/img/sleep${size}x${size}.png')`
    },
  },
  mounted () {
    if (typeof IntersectionObserver === 'undefined' || !(this.image || this.user?.avatar)) {
      this.lazyLoaded = true
      return
    }
    lazyLoad(this.$el, () => { this.lazyLoaded = true })
  },
  methods: {
    intersectionHandler (evt) {
      const isIntersecting = evt[evt.length - 1].isIntersecting
      if (isIntersecting) {
        this.lazyLoaded = true
        this.observer.disconnect()
        this.observer = null
      }
    },
  },
}
</script>
<style scoped lang="scss">
::v-deep.unobtrusive-link { // Disable default link styling
  text-decoration: none !important;
}

::v-deep.b-avatar.btn:not(:disabled):not(.disabled):hover .b-avatar-img img, ::v-deep.b-avatar[href]:not(:disabled):not(.disabled):hover .b-avatar-img img { // Disable default avatar image zoom on hover
    transform: none !important;
}

::v-deep {
  .avatar-icon {
    font-size: 1.4em;
  }
  .b-avatar-badge {
    font-size: v-bind("badgeSize") !important;
    & > span > span { // increase padding for text-badges to improve readability
      padding: 3px;
    }
    &.badge-primary { // slightly brighter border for primary badges to improve contrast
      border: 1px solid var(--fs-color-primary-400);
      padding: calc(0.25em - 2px); // reduce size to compensate for added border
      & > span { // reposition the text centered
        position: relative;
        top: 1px;
      }
    }
  }
}

.sleep::after { // Sleeping hats
  content: '';
  background-image: v-bind('sleepImageSrc');
  display: block;
  height: 100%;
  width: 100%;
  background-repeat: no-repeat;
  background-size: contain;
  position: absolute;
  top: -14%;
  left: -37%;
}

.sleep.avatar-transparent::after {
  opacity: 0.5;
}

.avatar-light:has(.avatar-icon) { // Styling light icon avatars
  border: 1px solid var(--fs-border-default);
}.avatar-light .avatar-icon {
  color: var(--fs-color-primary-500);
}
.avatar-transparent::v-deep img {
  opacity: 0.5;
}
</style>

<template>
  <div
    v-if="!isSet"
    class="information-field alert alert-info d-flex justify-content-between"
  >
    <i
      v-if="icon"
      class="fas info-icon align-self-center mr-3 d-none d-md-block "
      :class="icon"
    />
    <div class="flex-grow-1 d-flex flex-column justify-content-between">
      <div class="alignt-self-start">
        <h4 v-if="title" v-text="title" />
        <!-- eslint-disable vue/no-v-html -->
        <!-- Sanitized in src/Modules/Content/ContentGateway.php get() -->
        <p
          v-if="description"
          class="description mb-1 w-md-50"
          v-html="description"
        />
        <!-- eslint-enable -->
      </div>
      <div
        v-if="links.length > 0"
        class="d-flex align-items-center my-2"
      >
        <FsLink
          v-for="(link, key) in links"
          :key="key"
          class="btn btn-sm btn-info font-weight-bold align-self-start mr-2"
          :to="link.urlShortHand ? $url(link.urlShortHand) : link.href"
        >
          {{ $t(link.text) }}
        </FsLink>
      </div>
    </div>
    <i
      v-if="isCloseable"
      class="close-interaction fas fa-times"
      @click="close"
    />
  </div>
</template>

<script>
// Stores
import { mutations } from '@/stores/calendar'
// Mixin
import RouteAndDeviceCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'
import { HTTP_RESPONSE } from '@/consts'
// Components
import FsLink from '@/components/UI/FsLink.vue'

export default {
  components: { FsLink },
  mixins: [RouteAndDeviceCheckMixin],
  props: {
    type: { type: String, default: 'info' },
    tag: { type: String, default: '' },
    icon: { type: String, default: '' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    isCloseable: { type: Boolean, default: true },
    links: { type: Array, default: () => [] },
  },
  data () {
    return {}
  },
  computed: {
    isSet () {
      return JSON.parse(localStorage.getItem(this.tag))
    },
  },
  async created () {
    if (!this.isSet && this.type === 'calendar') {
      try {
        await mutations.fetchToken()
        this.close()
      } catch (hidden) {
        if (hidden.code === HTTP_RESPONSE.NOT_FOUND) return {}
      }
    }
    if (!this.isSet && this.type === 'push' && this.isSafari) {
      this.close()
    }
  },
  methods: {
    setSeen () {
      if (this.isCloseable) {
        localStorage.setItem(this.tag, JSON.stringify(true))
      }
    },
    close () {
      this.setSeen()
      this.remove()
    },
    remove () {
      this.$emit('close')
      this.$destroy()
      if (this.$el?.parentNode) {
        this.$el.parentNode.removeChild(this.$el)
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.information-field {
  min-height: 100px;
}

.alert-broadcast {
  min-height: 0;
  align-content: center;
}

.info-icon {
  font-size: 3rem;
}

.close-interaction {
  cursor: pointer;

  &:hover {
    color: var(--fs-color-primary-500);
  }
}

.list-group-item-action {
  cursor: pointer;
}

::v-deep.description a {
  text-decoration: underline;
}
</style>

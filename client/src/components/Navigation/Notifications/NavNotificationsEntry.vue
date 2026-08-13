<template>
  <div>
    <a
      class="dropdown-header dropdown-item d-flex justify-content-between align-items-center gap"
      :class="classes"
      :href="isTranslationFailed || !bell.href ? '#' : bell.href"
      @click="handleClick"
      @auxclick="handleAuxClick"
    >
      <Avatar
        class="mr-2"
        :image="bell.image"
        :icon="getIcon"
        variant="light"
        href=""
        @click.stop.prevent="closeBell"
      />
      <span class="flex-grow-1 d-flex flex-column text-truncate">
        <span class="d-flex justify-content-between align-items-center text-truncate">
          <span
            class="mb-1 text-truncate"
            v-text="isTranslationFailed ? $t('bell.translation_failed.title') : $t(`bell.${bell.title}`, bell.payload)"
          />
          <TimeDisplay
            class="font-weight-normal"
            :time="bell.createdAt"
          />
        </span>
        <small
          class="position-relative"
        >
          <span class="text-truncate d-inline-block w-100 text-preview">
            {{ isTranslationFailed ? $t('bell.translation_failed.key') : $t(`bell.${bell.key}`, bell.payload) }}
          </span>
        </small>
      </span>
      <b-button
        v-b-tooltip.noninteractive="$t(`bell.mark_as.${!bell.isRead ? 'read' : 'unread'}`)"
        size="sm"
        variant="outline-secondary"
        class="mark-read-button"
        @click.stop.prevent="toggleReadStatus()"
      >
        <i
          :class="`fas fa-eye${!bell.isRead ? '' : '-slash'}`"
        />
      </b-button>
    </a>
    <TranslationFailedModal
      v-if="isTranslationFailed"
      :id="translationFailedModalId"
      :href="bell.href"
      :bell-title="`bell.${bell.title}`"
      :bell-key="`bell.${bell.key}`"
    />
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import TimeDisplay from '@/components/TimeDisplay.vue'
import DataBell from '@/stores/bells'
import { pulseError } from '@/script'
import { navigate } from '@/helper/router'
import TranslationFailedModal from './TranslationFailedModal.vue'

export default {
  components: { Avatar, TimeDisplay, TranslationFailedModal },
  mixins: [MediaQueryMixin],
  props: {
    bell: { type: Object, default: () => ({}) },
  },
  data () {
    return {
      translationFailedModalId: `translation-failed-modal-${this._uid}`,
    }
  },
  computed: {
    classes () {
      return [
        !this.bell.isRead ? 'list-group-item-warning' : null,
        this.bell.isDeleting ? 'disabledLoading' : null,
      ]
    },
    isTranslationFailed () {
      // Check if the title translation failed
      const titleTranslation = this.$t(`bell.${this.bell.title}`, this.bell.payload)
      const rawTitle = `bell.${this.bell.title}`

      // Check if the key translation failed
      const keyTranslation = this.$t(`bell.${this.bell.key}`, this.bell.payload)
      const rawKey = `bell.${this.bell.key}`

      return titleTranslation === rawTitle || keyTranslation === rawKey
    },
    getIcon () {
      if (this.isTranslationFailed) {
        return 'fas fa-flask-vial'
      } else if (this.bell.image) {
        return undefined
      } else {
        return this.bell.icon
      }
    },
  },
  methods: {
    closeBell () {
      if (this.bell.isCloseable) {
        this.$emit('remove', this.bell.id)
      }
    },
    async toggleReadStatus () {
      try {
        await DataBell.mutations.setReadStatus(this.bell, !this.bell.isRead)
      } catch (err) {
        pulseError(this.$t('error_unexpected'))
      }
      document.activeElement.blur() // without this the entry is focused after clicking
    },
    handleClick (evt) {
      if (evt.metaKey || evt.ctrlKey || evt.shiftKey || evt.altKey) {
        return this.$emit('read', this.bell) // Emit and let browser handle
      }

      evt.preventDefault() // prevent the browser from navigating immediately
      this.$emit('read', this.bell)

      if (this.isTranslationFailed) {
        // eslint-disable-next-line vue/custom-event-name-casing
        this.$root.$emit('bv::show::modal', this.translationFailedModalId)
      } else {
        if (this.bell.href) {
          if (this.bell.href.startsWith('/')) {
            // `navigate` also notifies the page when the bell points at the url that is
            // already open, so that it refetches its content instead of doing nothing.
            navigate(this.bell.href).catch(err => console.error(err))
          } else {
            location.href = this.bell.href
          }
        }
      }
    },
    handleAuxClick (evt) {
      if (evt.button === 1) {
        // Emit on middle-click (open link in new tab)
        this.$emit('read', this.bell)
      }
    },
  },
}
</script>

<style lang="scss" scoped>
::v-deep.b-avatar :hover .avatar-icon::before {
  content: "\f00d"; // Change icon to "fa-times"
}

.gap {
  gap: 0.5rem;
}

.mark-read-button {
  display: none;
}

.dropdown-item:hover .mark-read-button {
  display: inline;
}

@media (max-width: 768px) {
  .mark-read-button {
    display: inline;
  }
}
</style>

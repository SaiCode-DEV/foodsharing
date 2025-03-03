<template>
  <a
    class="dropdown-header dropdown-item d-flex justify-content-between align-items-center gap"
    :class="classes"
    :href="bell.href"
    @click="$emit('read', bell)"
  >
    <Avatar
      class="mr-2"
      :image="bell.image"
      :icon="bell.image ? undefined : bell.icon"
      variant="light"
      href="#"
      @click.stop="closeBell"
    />
    <span class="flex-grow-1 d-flex flex-column text-truncate">
      <span class="d-flex justify-content-between align-items-center text-truncate">
        <span
          class="mb-1 text-truncate"
          v-text="$i18n(`bell.${bell.title}`, bell.payload)"
        />
        <Time
          class="font-weight-normal"
          :time="bell.createdAt"
        />
      </span>
      <small
        class="position-relative"
      >
        <span class="text-truncate d-inline-block w-100 text-preview">
          {{ $i18n(`bell.${bell.key}`, bell.payload) }}
        </span>
      </small>
    </span>
    <b-button
      v-b-tooltip.noninteractive="$i18n(`bell.mark_as.${!bell.isRead ? 'read' : 'unread'}`)"
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
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import Time from '@/components/Time.vue'
import DataBell from '@/stores/bells'
import { pulseError } from '@/script'

export default {
  components: { Avatar, Time },
  mixins: [MediaQueryMixin],
  props: {
    bell: { type: Object, default: () => ({}) },
  },
  computed: {
    classes () {
      return [
        !this.bell.isRead ? 'list-group-item-warning' : null,
        this.bell.isDeleting ? 'disabledLoading' : null,
      ]
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
        pulseError(this.$i18n('error_unexpected'))
      }
      document.activeElement.blur() // without this the entry is focused after clicking
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

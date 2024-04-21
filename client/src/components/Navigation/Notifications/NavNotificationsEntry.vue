<template>
  <a
    class="dropdown-header dropdown-item d-flex justify-content-between align-items-center"
    :class="{
      'list-group-item-warning': !bell.isRead,
      'disabledLoading': bell.isDeleting,
    }"
    :href="bell.href"
    @click="$emit('read', bell)"
  >
    <Avatar
      class="mr-2"
      :image="bell.image"
      :icon="bell.image ? undefined : bell.icon"
      variant="light"
      href="#"
      @click="closeBell()"
    />
    <span class="d-flex w-100 flex-column text-truncate">
      <span class="d-flex justify-content-between align-items-center text-truncate">
        <span
          class="mb-1 text-truncate"
          v-text="$i18n(`bell.${bell.title}`, bell.payload)"
        />
        <small class="text-muted text-right nowrap">
          {{ $dateFormatter.relativeTime(new Date(bell.createdAt)) }}
        </small>
      </span>
      <small
        class="text-truncate"
        v-text="$i18n(`bell.${bell.key}`, bell.payload)"
      />
    </span>
  </a>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: { Avatar },
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
  },
}
</script>

<style lang="scss" scoped>
::v-deep.b-avatar :hover .avatar-icon::before {
  content: "\f00d"; // Change icon to "fa-times"
}
</style>

<template>
  <container
    :title="$i18n('globals.type.groups_menu')"
    class="bg-white"
  >
    <NavGroupsLinkEntry
      :entry="groupMenu"
      :application-count="applicationCount"
      :is-linking-subpages="false"
      @change-page="$emit('change-page', $event.valueOf())"
    />
  </container>
</template>

<script>
import NavGroupsLinkEntry from '@/components/Navigation/Groups/NavGroupsLinkEntry.vue'
import Container from '@/components/Container/Container.vue'

export default {
  components: { Container, NavGroupsLinkEntry },
  props: {
    applications: { type: Array, required: true },
    groupMenu: { type: Object, required: true },
  },
  computed: {
    applicationCount () {
      return this.applications.length
    },
  },
}
</script>

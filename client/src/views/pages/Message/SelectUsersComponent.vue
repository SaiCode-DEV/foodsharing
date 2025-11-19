<template>
  <multiselect
    v-model="selectedUsers"
    label="value"
    track-by="id"
    :placeholder="$t('chat.select_recipients')"
    open-direction="bottom"
    :options="users"
    :multiple="true"
    :searchable="true"
    :loading="isLoading"
    :internal-search="false"
    :clear-on-select="true"
    :close-on-select="false"
    :options-limit="300"
    :max-height="600"
    :show-no-results="false"
    :show-no-options="false"
    :hide-selected="true"
    @search-change="searchUsers"
    @input="selectedUsersChanged"
  />
</template>

<script>
// Stores
import { searchUser } from '@/api/search'
import { pulseError } from '@/script'

import Multiselect from 'vue-multiselect'

export default {
  components: {
    Multiselect,
  },
  props: {
    selectUsers: {
      type: Array,
      default () {
        return []
      },
    },
  },
  data () {
    return {
      selectedUsers: this.selectUsers,
      users: [],
      isLoading: false,
    }
  },
  methods: {
    clearAll () {
      this.selectedUsers = []
    },
    async searchUsers (query) {
      this.isLoading = true

      // requests search results from the server
      let users = []
      if (query.length >= 3) {
        try {
          users = await searchUser(query)
        } catch (e) {
          pulseError(this.$t('error_unexpected'))
        }
      }
      this.users = users
      this.isLoading = false
    },
    selectedUsersChanged (users) {
      this.$emit('selected-users-changed', users)
    },
  },
}

</script>

<style src="vue-multiselect/dist/vue-multiselect.min.css"></style>

<style lang="scss" scoped>

  ::v-deep input[type="text"] {
    // revert shame-old-style-corrections.scss styling
    border: initial;
    transition: none; // otherwise the border has a flickering when leaving the search area
  }

</style>

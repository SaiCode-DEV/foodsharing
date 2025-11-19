<template>
  <b-modal
    id="BuddiesModal"
    :title="$t('buddy.my') + ' (' + numbuddies + ')'"
    hide-footer
    @show="fetchBuddies"
  >
    <div v-if="loading" class="text-center my-5">
      <b-spinner label="Loading..." />
    </div>
    <template v-for="section in buddySections">
      <div
        v-if="!loading && section.list.length"
        :key="section.title"
        class="mb-4"
      >
        <h4>{{ section.title }} ({{ section.list.length }})</h4>
        <ul class="list-unstyled">
          <li
            v-for="buddy in section.list"
            :key="buddy.id"
            class="d-flex align-items-center mb-2"
          >
            <Avatar
              :user="{ id: section.profileId(buddy), name: buddy.name, avatar: buddy.photo }"
              :size="35"
              tooltip=""
              class="mr-2"
            />
            <a :href="$url('profile', section.profileId(buddy))" class="d-flex align-items-center">
              {{ buddy.name }}
            </a>
          </li>
        </ul>
      </div>
    </template>
    <div
      v-if="!loading && numbuddies === 0"
      class="text-center my-5"
    >
      <p>{{ $t('buddy.none') }}</p>
    </div>
  </b-modal>
</template>

<script>
import { get } from '@/api/base'
import Avatar from '@/components/Avatar/Avatar.vue'
export default {
  name: 'BuddiesModal',
  components: { Avatar },
  data () {
    return {
      buddies: [],
      loading: false,
      numbuddies: 0,
    }
  },
  computed: {
    buddySections () {
      if (!this.buddies) return []
      return [
        {
          title: this.$t('buddy.confirmed'),
          list: this.buddies.buddies || [],
          iconClass: 'fas fa-check-circle buddy-status-icon buddy-status-check',
          profileId: buddy => buddy.buddyId,
        },
        {
          title: this.$t('buddy.requests.mine'),
          list: this.buddies.requests?.mine || [],
          iconClass: 'fas fa-clock buddy-status-icon buddy-status-warn',
          profileId: buddy => buddy.buddyId,
        },
        {
          title: this.$t('buddy.requests.other'),
          list: this.buddies.requests?.other || [],
          iconClass: 'fas fa-question-circle buddy-status-icon buddy-status-warn',
          profileId: buddy => buddy.fsId,
        },
      ]
    },
  },
  methods: {
    async fetchBuddies () {
      this.loading = true
      try {
        const res = await get('/buddy/list')
        this.buddies = res || null
        this.numbuddies = this.buddies ? this.buddies.buddies.length + this.buddies.requests.mine.length + this.buddies.requests.other.length : 0
      } catch (e) {
        this.buddies = null
      }
      this.loading = false
    },
  },
}
</script>

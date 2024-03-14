<template>
  <div>
    <b-tabs content-class="mt-3">
      <b-tab
        :title="isGlobalWorkingGroup ? $i18n('sidenav.superregional') : $i18n('sidenav.localgroups')"
        active
      >
        <input
          v-model="filterText"
          class="mb-3"
          type="text"
          :placeholder="$i18n('group.filter_placeholder')"
        >
        <Container
          v-for="group in filteredGroups"
          :key="group.id"
          :tag="'groups.overview.' + group.id"
          :title="group.name"
          :tooltip-key="group.function_tooltip_key ? $i18n(group.function_tooltip_key) : null"
          :container-is-expanded="isContainerExpanded"
        >
          <div class="list-group-item">
            <b-row>
              <b-col>
                <Avatar
                  v-for="leader in group.leaders"
                  :key="leader.id"
                  :user="{ id: leader.id, name: leader.name, avatar: leader.photo }"
                  :size="50"
                  :href="$url('profile', leader.id)"
                />
                <div>
                  <strong>{{ translateCounted('group.admin_count', group.leaders.length) }}</strong>
                  <p>{{ translateCounted('group.member_count', group.membersCount) }}</p>
                  <p>{{ group.teaser }}</p>
                  <p><a :href="$url('mailto_mail_foodsharing_network', group.email)">{{ group.email }}</a></p>
                </div>
              </b-col>
              <b-col>
                <img
                  v-if="group.image"
                  :src="group.image"
                >
              </b-col>
            </b-row>
            <div class="float-right m-2">
              <b-button
                variant="primary"
                @click="openContactModal(group)"
              >
                {{ $i18n('group.actions.contact') }}
              </b-button>
              <b-button
                v-if="group.mayEdit"
                variant="primary"
                :href="$url('workingGroupEdit', group.id)"
              >
                {{ $i18n('group.actions.edit') }}
              </b-button>
              <b-button
                v-if="group.mayAccess"
                variant="primary"
                :href="$url('workingGroup', group.id)"
              >
                {{ $i18n('group.actions.go') }}
              </b-button>
              <b-button
                v-if="group.mayJoin"
                variant="primary"
                @click="joinGroup(group.id)"
              >
                {{ $i18n('group.actions.join') }}
              </b-button>
              <b-button
                v-else-if="group.mayApply"
                variant="primary"
                @click="openRequestModal(group)"
              >
                {{ $i18n('group.actions.apply') }}
              </b-button>
            </div>
          </div>
        </container>
      </b-tab>
      <b-tab
        v-for="(navItem, index) in navItems"
        :key="index"
        :title="navItem.title"
      >
        <b-list-group>
          <b-list-group-item
            v-for="item in navItem.items"
            :key="item.name"
          >
            <a :href="item.href">{{ item.name }}</a>
          </b-list-group-item>
        </b-list-group>
      </b-tab>
    </b-tabs>
    <b-modal
      ref="groupContactForm"
      :title="$i18n('group.contact.title', {group: selectedGroupName})"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.send')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="trySendMail(selectedGroupId)"
    >
      <p>
        {{ $i18n('group.contact.disclaimer') }}
      </p>
      <label>{{ $i18n('terminology.message') }}:</label>
      <b-form-textarea
        id="contactmessage"
        v-model="contactMessage"
        rows="4"
      />
    </b-modal>
    <b-modal
      ref="groupRequestForm"
      :title="$i18n('group.application_region', {group: selectedGroupName})"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.send')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="trySendRequest(selectedGroupId)"
    >
      <p>
        <label>{{ $i18n('group.apply.motivation', { group: selectedGroupName }) }}</label>
        <b-form-textarea
          id="input-motivation"
          v-model="motivation"
          rows="2"
          class="mt-1"
        />
      </p>
      <p>
        <label>{{ $i18n('group.apply.ability') }}</label>
        <b-form-textarea
          id="input-ability"
          v-model="ability"
          rows="2"
          class="mt-1"
        />
      </p>
      <p>
        <label>{{ $i18n('group.apply.experience') }}</label>
        <b-form-textarea
          id="input-experience"
          v-model="experience"
          rows="2"
          class="mt-1"
        />
      </p>
      <p>
        <label>{{ $i18n('group.apply.how_much_time') }}</label>
        <b-form-select
          id="input-time"
          v-model="selectedTime"
          :options="timeOptions"
          size="sm"
          class="mt-1"
        />
      </p>
    </b-modal>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import Avatar from '@/components/Avatar/Avatar.vue'
import { addMember, sendMail, sendRequest } from '@/api/groups'
import { pulseError, pulseSuccess } from '@/script'
import i18n from '@/helper/i18n'
import UserData from '@/stores/user'

export default {
  name: 'Groups',
  components: { Avatar, Container },
  props: {
    groups: { type: Array, required: true },
    nav: { type: Object, required: true },
    isGlobalWorkingGroup: { type: Boolean, required: true },
  },
  data () {
    return {
      isContainerExpanded: false,
      filterText: '',
      filteredGroups: [],
      contactMessage: '',
      selectedGroupId: null,
      selectedGroupName: '',
      motivation: '',
      ability: '',
      experience: '',
      selectedTime: null,
      timeOptions: [1, 2, 3, 5].map(i => ({ value: i, text: this.$i18n(`group.apply.time.${i}`) })),
    }
  },
  computed: {
    navItems () {
      return [
        { title: this.$i18n('sidenav.yourregions'), items: this.nav.local },
        { title: this.$i18n('sidenav.yourgroups'), items: this.nav.groups },
      ]
    },
    userId () {
      return UserData.getters.getUserId()
    },
  },
  watch: {
    filterText (newVal) {
      this.filterGroups(newVal)
    },
  },
  mounted () {
    this.filteredGroups = this.groups
  },
  methods: {
    openContactModal (group) {
      this.selectedGroupId = group.id
      this.selectedGroupName = group.name
      this.$refs.groupContactForm.show()
    },
    openRequestModal (group) {
      this.selectedGroupId = group.id
      this.selectedGroupName = group.name
      this.$refs.groupRequestForm.show()
    },
    translateCounted (key, count) {
      const translationKey = key + '.' + this.getPluralKey(count)
      return this.$i18n(translationKey, { count: count })
    },
    getPluralKey (count) {
      if (count === 0) {
        return 'zero'
      } else if (count === 1) {
        return 'one'
      } else {
        return 'other'
      }
    },
    filterGroups (filterText) {
      this.filteredGroups = this.groups.filter(group =>
        group.name.toLowerCase().includes(filterText.toLowerCase()),
      )
    },
    async trySendMail (groupId) {
      try {
        await sendMail(groupId, this.contactMessage)
        pulseSuccess(i18n('success'))
      } catch (err) {
        pulseError(`${i18n('error_unexpected')}<br><br> ${err.message}`)
      }
    },
    async trySendRequest (groupId) {
      try {
        await sendRequest(groupId, this.motivation, this.ability, this.experience, this.selectedTime)
        pulseSuccess(i18n('success'))
      } catch (err) {
        pulseError(`${i18n('error_unexpected')}<br><br> ${err.message}`)
      }
    },
    async joinGroup (groupId) {
      try {
        await addMember(groupId, this.userId)
        window.location.href = this.$url('relogin_and_redirect_to_url', this.$url('workingGroup', groupId))
      } catch (e) {
        pulseError(`${i18n('error_unexpected')}<br><br> ${e.message}`)
      }
    },
  },
}
</script>

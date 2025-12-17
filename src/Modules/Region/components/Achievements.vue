<template>
  <div>
    <Container
      :title="$t('terminology.achievements')"
      info-key="achievements"
      wrap-content
    >
      <p v-text="$t('achievements.inThis.' + (isWorkGroup ? 'group' : 'region'))" />
      <Achievements
        :achievements="achievements"
        :no-modal="mayAdministrateAchievements"
        @click="select"
      />
    </Container>
    <Container v-if="selected">
      <template #title>
        <h5>
          <i :class="selected.icon ?? 'fas fa-tag'" />
          {{ selected.name }}
        </h5>
      </template>
      <div class="list-group-item">
        <AchievementInfo :achievement="selected" :scope-name="groupName" />
      </div>
      <div class="list-group-item">
        <h6 v-text="$t('achievements.award')" />
        <b-form-group :label="$t('achievements.findUser')">
          <b-row class="m-0">
            <UserSearchInput
              :placeholder="$t('store.sm.searchPlaceholder')"
              button-icon="fa-user-tag"
              :button-tooltip="$t('achievements.award')"
              :filter="searchFilter"
              :region-id="groupId"
              class="flex-grow-1 mr-2"
              @user-selected="awardAchievement"
            />
            <b-button
              v-b-tooltip="$t('achievements.editDetails')"
              v-b-toggle.formDetailsCollapse
              :pressed.sync="showFormDetails"
              variant="outline-secondary"
            >
              <i class="fas fa-cog" />
            </b-button>
          </b-row>
        </b-form-group>
        <b-collapse id="formDetailsCollapse">
          <b-form-group :label="$t('achievements.notice')">
            <b-form-input v-model="awardFormData.notice" />
          </b-form-group>
          <b-form-group :label="$t('achievements.validUntil')">
            <DatePicker
              v-model="awardFormData.validUntil"
              :reset-button="Boolean(awardFormData.validUntil)"
              :placeholder="$t('achievements.validity.indefiniteShort')"
              :label-reset-button="$t('achievements.validity.indefiniteShort')"
              :min="new Date()"
            />
          </b-form-group>
        </b-collapse>
      </div>
      <div class="list-group-item">
        <b-skeleton-table
          v-if="!selected.awardedUsers"
          :rows="3"
          :columns="4"
        />
        <b-table
          v-else-if="selected.awardedUsers.length"
          :items="selected.awardedUsers"
          :fields="tableFields"
          :per-page="perPage"
          :current-page="currentPage"
          sort-icon-left
          sort-null-last
        >
          <template #cell(createdAt)="row">
            <Time :time="row.item.createdAt" :show-icon="false" />
          </template>
          <template #cell(user)="row">
            <Avatar :user="row.item.user" />
            <a :href="$url('profile', row.item.user.id)">{{ row.item.user.name }}</a>
          </template>
          <template #cell(reviewer)="row">
            <div v-if="row.item.reviewer">
              <Avatar :user="row.item.reviewer" />
              <a :href="$url('profile', row.item.reviewer.id)">{{ row.item.reviewer.name }}</a>
            </div>
            <span v-else v-text="$t('achievements.awardedNoReviewer')" />
          </template>
          <template #cell(validUntil)="row">
            <Time
              :time="row.item.validUntil"
              :show-icon="false"
              :fallback="$t('achievements.validity.indefiniteShort')"
            />
          </template>
          <template #cell(actions)="row">
            <OverflowMenu
              v-if="row.item.user.id !== ownId"
              :options="[
                {icon:'trash-alt', textKey: 'achievements.revoke', callback: () => revokeAchievement(row.item.id) },
                {icon:'pen', textKey: 'button.edit', callback: () => openEditModal(row.item) },
              ]"
            />
          </template>
        </b-table>
        <b-alert v-else show>
          {{ $t('achievements.notAwarded') }}
        </b-alert>
        <b-pagination
          v-if="(selected.awardedUsers?.length ?? 0) > perPage"
          v-model="currentPage"
          :total-rows="selected.awardedUsers.length"
          :per-page="perPage"
          class="float-right my-0"
        />
        <!-- TODO use PaginatedContent Component -->
      </div>
      <b-modal
        ref="editModal"
        :title="$t('achievements.editAwarded', { achievement: selected.name, user: editFormData.user?.name })"
        centered
        :ok-title="$t('button.save')"
        :cancel-title="$t('button.cancel')"
        @ok="editAchievement"
      >
        <b-form-group :label="$t('achievements.notice')">
          <b-form-input v-model="editFormData.notice" />
        </b-form-group>
        <b-form-group :label="$t('achievements.validUntil')">
          <DatePicker
            v-model="editFormData.validUntil"
            :reset-button="Boolean(editFormData.validUntil)"
            :placeholder="$t('achievements.validity.indefiniteShort')"
            :label-reset-button="$t('achievements.validity.indefiniteShort')"
            :min="new Date()"
          />
        </b-form-group>
      </b-modal>
    </Container>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import Achievements from '@/components/Achievement/Achievements.vue'
import * as api from '@/api/achievements.js'
import UserSearchInput from '@/components/UserSearchInput.vue'
import Avatar from '@/components/Avatar/Avatar.vue'
import Time from '@/components/Time.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import DatePicker from '@/components/DateTime/DatePicker.vue'
import { useUserStore } from '@/stores/user'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { ACHIEVEMENT_DUPLICATE_MODE } from '@/consts'
import AchievementInfo from '@/components/Achievement/AchievementInfo.vue'

const userStore = useUserStore()

export default {
  components: { Container, Achievements, UserSearchInput, Avatar, Time, OverflowMenu, DatePicker, AchievementInfo },
  props: {
    groupName: { type: String, required: true },
    groupId: { type: Number, required: true },
    isWorkGroup: { type: Boolean, default: false },
    mayAdministrateAchievements: { type: Boolean, default: false },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data: () => ({
    achievements: null,
    selected: null,
    showFormDetails: false,
    awardFormData: {},
    editFormData: {},
    currentPage: 1,
    perPage: 10,
  }),
  computed: {
    tableFields () {
      if (!this.selected.awardedUsers) return []
      const fields = [
        { key: 'createdAt', sortable: true, label: this.$t('achievements.awarded') },
        { key: 'user', sortable: true, sortByFormatted: true, label: this.$t('achievements.awardedTo'), formatter: (x) => x.name },
        { key: 'reviewer', sortable: true, sortByFormatted: true, label: this.$t('achievements.reviewer'), formatter: (x) => x?.name },
      ]
      if (this.selected.awardedUsers.find(awarded => awarded.notice)) {
        fields.push({ key: 'notice', label: this.$t('achievements.notice') })
      }
      if (this.selected.awardedUsers.find(awarded => awarded.validUntil)) {
        fields.push({ key: 'validUntil', sortable: true, label: this.$t('achievements.validUntil') })
      }
      fields.push({ key: 'actions', label: '' })
      return fields
    },
    ownId () {
      return userStore.getUserId
    },
  },
  async mounted () {
    this.achievements = await api.getAchievementsFromRegion(this.groupId)
    this.achievements.forEach(x => { this.$set(x, 'awardedUsers', null) })
  },
  methods: {
    async select (achievement) {
      if (!this.mayAdministrateAchievements) return
      this.selected = achievement
      if (!achievement.awardedUsers) {
        achievement.awardedUsers = await api.getAwardedUsersForAchievement(achievement.id)
      }
      this.currentPage = 1
      let validUntil = null
      if (this.selected.validityInDaysAfterAssignment) {
        validUntil = new Date()
        validUntil.setDate(validUntil.getDate() + this.selected.validityInDaysAfterAssignment)
      }
      this.awardFormData.validUntil = validUntil
    },
    async awardAchievement (userId) {
      let options = {}
      if (this.showFormDetails) {
        options = {
          notice: this.awardFormData.notice,
          validUntil: this.prepareDate(this.awardFormData.validUntil),
        }
      }
      const awardedUser = await api.awardAchievement(userId, this.selected.id, options)
      this.selected.awardedUsers.unshift(awardedUser)
    },
    async revokeAchievement (awardedAchievementId) {
      const index = this.selected.awardedUsers.findIndex(awarded => awarded.id === awardedAchievementId)
      if (!await this.confirmationDialogue('achievements.revokeConfirmation', { params: this.selected.awardedUsers[index].user })) return
      await api.revokeAchievement(awardedAchievementId)
      this.selected.awardedUsers.splice(this.selected.awardedUsers.findIndex(awarded => awarded.id === awardedAchievementId), 1)
    },
    openEditModal (awardedAchievement) {
      this.editFormData = Object.assign({}, awardedAchievement)
      this.$refs.editModal.show()
    },
    prepareDate (date) {
      if (date) {
        // Change from start of the day to current time
        const now = new Date()
        date = new Date(date)
        date.setHours(now.getHours(), now.getMinutes(), now.getSeconds(), 0)
        return date
      }
      return 'infinite'
    },
    async editAchievement () {
      const options = {
        notice: this.editFormData.notice,
        validUntil: this.prepareDate(this.editFormData.validUntil),
      }
      const awardedUser = await api.editAchievement(this.editFormData.id, options)
      const index = this.selected.awardedUsers.findIndex(awarded => awarded.id === this.editFormData.id)
      this.$set(this.selected.awardedUsers, index, awardedUser)
    },
    searchFilter (userId) {
      if (!this.selected.awardedUsers) return false
      if (userId === this.ownId) return false
      if (this.selected.duplicateMode === ACHIEVEMENT_DUPLICATE_MODE.MULTIPLE) return true
      return !this.selected.awardedUsers.some(awarded => awarded.user.id === userId)
    },
  },
}
</script>
<style scoped>
::v-deep th {
  border-top: 0;
}
::v-deep td {
  vertical-align: middle;
}
.achievements-loader ::v-deep .b-skeleton {
  display: inline-block;
  border-radius: 1em;
  margin-right: 0.5em;
}
</style>

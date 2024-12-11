<template>
  <Container :title="title">
    <slot v-if="isWorkGroup" name="user-search-input">
      <div class="row p-2">
        <div class="col col-12 col-md-6">
          <user-search-input
            v-if="mayEditMembers"
            id="new-foodsaver-search"
            :placeholder="$i18n('search.user_search.placeholder')"
            button-icon="fa-user-plus"
            :button-tooltip="$i18n('group.member_list.add_member')"
            :filter="!containsMember"
            @user-selected="addNewTeamMember"
          />
        </div>
      </div>
    </slot>
    <b-tabs
      v-if="!isWorkGroup && mayEditMembers"
      v-model="activeTab"
      content-class="mt-2"
      class="p-2"
    >
      <b-tab
        :title="$i18n('group.member_list.default.title')"
        active
      >
        <slot />
        <div class="row p-2">
          <div class="col col-md-6">
            <b-form-select
              v-model="filterRole"
              :options="roleOptions"
              size="xl"
            />
          </div>
          <div class="col col-md-6">
            <div class="row">
              <div class="col col-md-6">
                <b-form-checkbox
                  v-model="filterLastActivity"
                  switch
                  size="sm"
                >
                  {{ $i18n('group.filter_by_last_activity') }}
                </b-form-checkbox>
              </div>
              <div class="col col-md-6">
                <b-form-spinbutton
                  v-model="lastActivityFilterMonths"
                  min="1"
                  max="36"
                  size="sm"
                  :disabled="!filterLastActivity"
                />
              </div>
            </div>
          </div>
        </div>
      </b-tab>
      <b-tab v-if="!isWorkGroup && mayEditMembers" :title="$i18n('group.member_list.passports.title')">
        <div class="d-flex justify-content-between">
          <b-button
            :disabled="passportMember.length <= 0"
            variant="outline-primary"
            size="sm"
            @click="verifySelectedMember"
          >
            {{ $i18n('group.member_list.passports.verify_selected') }} ({{ passportMember.length }})
          </b-button>

          <div class="d-flex align-items-sm-baseline align-items-stretch justify-content-end">
            <b-form-checkbox
              v-if="isCreatePdf && passportMember.length === 1"
              v-model="usePaperSizeDinA4"
              class="ml-2"
              size="sm"
            >
              {{ $i18n('group.member_list.passports.automatic_paper_size') }}
            </b-form-checkbox>
            <b-form-checkbox
              v-model="isCreatePdf"
              class="ml-2"
              size="sm"
              @change="setPassportSettingsToLocalStorage"
            >
              {{ $i18n('group.member_list.passports.create_pdf') }}
            </b-form-checkbox>
            <b-form-checkbox
              v-model="isRenewPassport"
              class="ml-2 mr-2"
              size="sm"
              @change="setPassportSettingsToLocalStorage"
            >
              {{ $i18n('group.member_list.passports.active_or_renew_passport') }}
            </b-form-checkbox>
            <b-button
              :disabled="passportMember.length <= 0 || !(isCreatePdf || isRenewPassport)"
              variant="outline-primary"
              size="sm"
              @click="createPassports"
            >
              {{ $i18n('group.member_list.passports.execute') }} ({{ passportMember.length }})
            </b-button>
          </div>
        </div>
      </b-tab>
    </b-tabs>

    <b-container>
      <div v-if="regionStore.memberList.length" class="card-body p-0">
        <div class="form-row">
          <div class="filter-for-label">
            <label class=" col-form-label col-form-label-sm foo">
              {{ $i18n('list.filter_for') }}
            </label>
          </div>
          <div class="filter-for-form">
            <input
              id="filterMember"
              v-model="filterText"
              type="text"
              class="form-control form-control-sm"
              :placeholder="$i18n('filterlist.filter_for_name_id')"
            >
          </div>
          <b-button-group class="filter-for-search">
            <b-dropdown
              v-if="activeTab === ACTIVE_TAB_PASSPORT"
              id="dropdown-form"
              ref="dropdown"
              variant="link"
              toggle-class="text-decoration-none"
              no-caret
            >
              <template #button-content>
                <button
                  v-b-tooltip.hover
                  :title="$i18n('button.filter_options')"
                  type="button"
                  class="btn btn-sm"
                >
                  <i class="fas fa-filter" />
                </button>
              </template>

              <b-dropdown-form>
                <b-form-checkbox
                  v-model="filterPassportMember"
                  switch
                  size="sm"
                  class="mb-2"
                >
                  {{ $i18n('group.member_list.passports.filter_selection') }}
                </b-form-checkbox>

                <label class="mb-1">{{ $i18n('group.member_list.passports.show_only_member_after') }}</label>
                <b-form-select
                  v-model="filterPassportUntilValid"
                  :options="passportFilterOptions"
                  size="sm"
                  class="mb-2"
                />
              </b-dropdown-form>
            </b-dropdown>
            <button
              v-b-tooltip.hover
              :title="$i18n('button.clear_filter')"
              type="button"
              class="btn btn-sm"
              @click="clearFilter"
            >
              <i class="fas fa-times" />
            </button>
          </b-button-group>
        </div>
      </div>

      <b-table
        ref="selectableTable"
        :fields="filteredFields"
        :items="membersFiltered"
        :current-page="currentPage"
        :per-page="perPage"
        :sort-by="sortBy"
        :busy="isBusy"
        :select-mode="selectMode"
        small
        hover
        responsive
        class="foto-table"
        @sort-changed="sortBy = $event.sortBy ? $event.sortBy : ''"
      >
        <template #head(passportToggle)>
          <b-form-checkbox
            v-if="mayEditMembers && activeTab === ACTIVE_TAB_PASSPORT"
            :checked="selectAllTable"
            @change="toggleSelectAllTable"
          />
        </template>
        <template v-if="mayEditMembers" #cell(passportToggle)="row">
          <b-form-checkbox
            v-if="activeTab === ACTIVE_TAB_PASSPORT && !isNullOrEmptyOrWhitespace(row.item.avatar)"
            size="sm"
            :checked="containsPassportMember(row.item.id)"
            @change="togglePassportMember(row.item.id)"
          />
        </template>
        <template #cell(imageUrl)="row">
          <Avatar
            :user="row.item"
            :size="50"
          />
        </template>
        <template #cell(id)="row">
          <a
            :href="$url('profile', row.item.id)"
          >
            {{ row.item.id }}
          </a>
        </template>
        <template #cell(name)="row">
          <a
            :href="$url('profile', row.item.id)"
            :title="row.item.id"
          >
            {{ row.item.name }}
          </a>
        </template>
        <template #cell(lastName)="row">
          <a
            :href="$url('profile', row.item.id)"
            :title="row.item.id"
          >
            {{ row.item.lastName }}
          </a>
        </template>
        <template #cell(lastPassDate)="row">
          {{ row.item.lastPassDate === null ? $i18n('group.member_list.passports.never_before') : $dateFormatter.format(row.item.lastPassDate, {
            day: 'numeric',
            month: 'numeric',
            year: 'numeric',
          }) }}
        </template>
        <template #cell(passUntilValid)="row">
          {{
            row.item.lastPassDate === null ? '' : $dateFormatter.format(passportValidUntilDate(row.item.lastPassDate), {
              day: 'numeric',
              month: 'numeric',
              year: 'numeric',
            })
          }}
        </template>
        <template #cell(lastActivity)="row">
          {{ $dateFormatter.format(row.item.lastActivity, {
            day: 'numeric',
            month: 'numeric',
            year: 'numeric',
          }) }}
        </template>
        <template #cell(role)="row">
          {{ $i18n('terminology.role.' + row.item.role) }}
        </template>
        <template #cell(isVerified)="row">
          <button
            v-if="row.item.isVerified"
            class="btn btn-sm btn-primary"
            :title="$i18n('group.member_list.is_verified')"
            @click="changeVerification(false, row.item.id,row.item.name)"
          >
            <i class="fas fa-user-check" />
          </button>
          <button
            v-else
            class="btn btn-sm btn-secondary"
            :title="$i18n('group.member_list.not_verified')"
            @click="changeVerification(true, row.item.id,row.item.name)"
          >
            <i class="fas fa-user-check" />
          </button>
        </template>
        <template #cell(isHomeRegion)="row">
          <i
            v-if="row.item.isHomeRegion"
            class="fas fa-house-user"
            :title="$i18n('group.member_list.is_home_region')"
          />
        </template>
        <template #cell(adminButton)="row">
          <b-button
            v-if="getAdminButton(row.item)"
            v-b-tooltip.viewport="getAdminButton(row.item).title"
            size="sm"
            :variant="getAdminButton(row.item).variant"
            :disabled="isBusy"
            @click="getAdminButton(row.item).action(row.item)"
          >
            <i class="fas fa-fw" :class="getAdminButton(row.item).icon" />
          </b-button>
        </template>
        <template v-if="mayEditMembers" #cell(removeButton)="row">
          <b-button
            v-if="canRemoveMember(row.item)"
            v-b-tooltip.viewport="$i18n('group.member_list.remove_title')"
            size="sm"
            variant="danger"
            :disabled="isBusy"
            @click="removeMember(row.item)"
          >
            <i class="fas fa-fw fa-user-times" />
          </b-button>
        </template>
      </b-table>
      <div class="float-right p-1 pr-3">
        <b-pagination
          v-model="currentPage"
          :total-rows="membersFiltered.length"
          :per-page="perPage"
          class="my-0"
        />
      </div>
    </b-container>
  </Container>
</template>

<script>
import { addMember } from '@/api/groups'
import { removeMember, setAdminOrAmbassador, removeAdminOrAmbassador, getRegionMemberPermissions } from '@/api/regions'
import { useRegionStore } from '@/stores/regions'
import { hideLoader, pulseError, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import UserSearchInput from '@/components/UserSearchInput'
import { verifyUser, deverifyUser, createPassportAsAmbassador } from '@/api/verification'
import Container from '@/components/Container/Container.vue'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'
import Avatar from '@/components/Avatar/Avatar.vue'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import { REGION_IDS } from '@/consts'
import { PASSPORT_FILTER_OPTIONS, useUserStore } from '@/stores/user'

const regionStore = useRegionStore()
const userStore = useUserStore()

export default {
  components: { UserSearchInput, Container, Avatar },
  mixins: [ConfirmationDialogue, MediaQueryMixin],
  props: {
    groupId: { type: Number, required: true },
    regionName: {
      type: String,
      default: '',
    },
    regionId: { type: Number, required: true },
    isWorkGroup: {
      type: Boolean,
      default: false,
    },
  },
  setup () {
    return {
      regionStore,
    }
  },
  data () {
    return {
      ACTIVE_TAB_DEFAULT: 0,
      ACTIVE_TAB_PASSPORT: 1,
      currentPage: 1,
      perPage: 20,
      filterText: '',
      filterRole: null,
      filterLastActivity: false,
      lastActivityFilterMonths: 6,
      isBusy: false,
      roleOptions: [
        { value: null, text: i18n('group.member_list.all_roles') },
        { value: 1, text: i18n('terminology.role.1') },
        { value: 2, text: i18n('terminology.role.2') },
        { value: 3, text: i18n('terminology.role.3') },
        { value: 4, text: i18n('terminology.role.4') },
      ],
      selectMode: 'multi',
      passportMember: [],
      filterPassportMember: false,
      filterPassportUntilValid: null,
      usePaperSizeDinA4: false,
      activeTab: null,
      sortBy: '',
      mayEditMembers: false,
      maySetAdminOrAmbassador: false,
      mayRemoveAdminOrAmbassador: false,
      isCreatePdf: true,
      isRenewPassport: true,
      passportFilterOptions: [
        { text: i18n('group.member_list.passports.filter_options.no_filter'), value: PASSPORT_FILTER_OPTIONS.NO_FILTER },
        { text: i18n('group.member_list.passports.filter_options.no_passport'), value: PASSPORT_FILTER_OPTIONS.NO_PASSPORT },
        { text: i18n('group.member_list.passports.filter_options.with_passport'), value: PASSPORT_FILTER_OPTIONS.WITH_PASSPORT },
        { text: i18n('group.member_list.passports.filter_options.invalid_passport'), value: PASSPORT_FILTER_OPTIONS.INVALID_PASSPORT },
      ],
      selectAllTable: false,
    }
  },
  computed: {
    userId () {
      return userStore.getUserId
    },
    getAdminButton () {
      return (item) => {
        if (this.mayRemoveAdminOrAmbassador && this.rowItemIsAdminOrAmbassadorOfRegion(item)) {
          return {
            title: this.$i18n(this.isWorkGroup ? 'group.member_list.remove_admin_title' : 'group.member_list.remove_ambassador_title'),
            variant: 'danger',
            icon: 'fa-user-slash',
            action: this.degradeAdmin,
          }
        } else if (this.maySetAdminOrAmbassador && this.rowItemNotEqualUserId(this.userId, item.id) && this.roleCheckForRegionAndWorkGroup(this.isWorkGroup, item.role)) {
          return {
            title: this.$i18n(this.isWorkGroup ? 'group.member_list.set_admin_title' : 'group.member_list.set_ambassador_title'),
            variant: 'warning',
            icon: 'fa-user-graduate',
            action: this.makeAdmin,
          }
        }
        return null
      }
    },
    isSelected () {
      return this.selected.length > 0
    },
    title () {
      return `${this.isWorkGroup ? this.$i18n('memberlist.header_for_workgroup', { bezirk: this.regionName }) : this.$i18n('memberlist.header_for_district', { bezirk: this.regionName })} ${this.memberCount}`
    },
    memberCount () {
      return this.$i18n('filterlist.some_in_all', { some: this.membersFiltered.length, all: regionStore.memberList.length })
    },
    dateBeforeMonths () {
      const dateInPast = new Date()
      dateInPast.setMonth(dateInPast.getMonth() - this.lastActivityFilterMonths)
      return dateInPast
    },
    membersFiltered () {
      const filterText = this.filterText ? this.filterText.toLowerCase() : null

      return regionStore.memberList.filter((member) => {
        if (this.activeTab === this.ACTIVE_TAB_PASSPORT && !member.isHomeRegion) {
          return false
        }

        if (filterText) {
          const idMatches = member.id.toString().startsWith(filterText)
          const nameMatches = member.name.toLowerCase().includes(filterText)
          const lastNameMatches = this.activeTab === this.ACTIVE_TAB_PASSPORT && member.lastName.toLowerCase().includes(filterText)

          if (!(idMatches || nameMatches || lastNameMatches)) {
            return false
          }
        }

        if (this.activeTab !== this.ACTIVE_TAB_PASSPORT && this.filterRole !== null && member.role !== this.filterRole) {
          return false
        }

        if (this.activeTab !== this.ACTIVE_TAB_PASSPORT && this.filterLastActivity && Date.parse(member.lastActivity) > this.dateBeforeMonths) {
          return false
        }

        if (this.filterPassportMember && !this.passportMember.includes(member.id)) {
          return false
        }

        if (this.activeTab === this.ACTIVE_TAB_PASSPORT && this.filterPassportUntilValid === PASSPORT_FILTER_OPTIONS.INVALID_PASSPORT && this.isPassportValid(member.lastPassDate)) {
          return false
        }

        if (this.activeTab === this.ACTIVE_TAB_PASSPORT && this.filterPassportUntilValid === PASSPORT_FILTER_OPTIONS.WITH_PASSPORT && member.lastPassDate === null) {
          return false
        }

        if (this.activeTab === this.ACTIVE_TAB_PASSPORT && this.filterPassportUntilValid === PASSPORT_FILTER_OPTIONS.NO_PASSPORT && member.lastPassDate !== null) {
          return false
        }

        return true
      })
    },
    filteredFields () {
      const columns = []

      if (this.activeTab === this.ACTIVE_TAB_PASSPORT) {
        columns.push({
          key: 'passportToggle',
          label: '',
          sortable: false,
          class: 'align-middle',
        })
      }

      columns.push(
        {
          key: 'imageUrl',
          sortable: false,
          label: '',
          class: 'foto-column',
        },
        {
          key: 'name',
          label: this.$i18n('group.name'),
          sortable: true,
          class: 'align-middle',
        },
      )

      if (this.activeTab === this.ACTIVE_TAB_PASSPORT) {
        columns.push({
          key: 'lastName',
          label: this.$i18n('group.member_list.default.lastname'),
          sortable: true,
          class: 'align-middle',
        })
      }

      columns.push({
        key: 'id',
        label: this.$i18n('group.userId'),
        sortable: true,
        class: 'align-middle',
      })

      if (!this.isWorkGroup && this.activeTab === this.ACTIVE_TAB_PASSPORT) {
        columns.push({
          key: 'lastPassDate',
          label: this.$i18n('group.member_list.passports.created_at'),
          sortable: true,
          class: 'align-middle',
        },
        {
          key: 'passUntilValid',
          label: this.$i18n('group.valid_until'),
          sortable: true,
          class: 'align-middle',
        })
      }

      if (this.mayEditMembers) {
        columns.push(
          {
            key: 'lastActivity',
            label: this.$i18n('group.last_activity'),
            sortable: true,
            class: 'align-middle',
          },
        )
      }

      if (this.mayEditMembers && this.activeTab === this.ACTIVE_TAB_DEFAULT) {
        columns.push(
          {
            key: 'role',
            label: this.$i18n('group.role_name'),
            sortable: true,
            class: 'align-middle',
          },
        )
      }

      if (this.activeTab === this.ACTIVE_TAB_PASSPORT) {
        columns.push(
          {
            key: 'isVerified',
            label: this.$i18n('group.member_list.is_verified'),
            sortable: true,
            class: 'align-middle',
          },
        )
      }

      if (this.mayEditMembers && this.activeTab === this.ACTIVE_TAB_DEFAULT) {
        columns.push(
          {
            key: 'isHomeRegion',
            label: this.$i18n('group.member_list.is_home_region'),
            sortable: true,
            class: 'align-middle',
          },
        )
      }

      if ((this.mayEditMembers && this.activeTab === this.ACTIVE_TAB_DEFAULT && !this.isWorkGroup) || this.mayEditMembers) {
        columns.push({
          key: 'adminButton',
          label: '',
          sortable: false,
          class: 'button-column',
        },
        {
          key: 'removeButton',
          label: '',
          sortable: false,
          class: 'button-column',
        })
      }

      return columns
    },
    adminName () {
      return this.isWorkGroup ? 'admin' : 'ambassador'
    },
    /*
     @TODO: This deactivates member lists for Europe and countries because it needs to much memory on the server.
     Can be remove when there is pagination.
     */
    isDeactivatedRegion () {
      return [REGION_IDS.EUROPE, REGION_IDS.GERMANY, REGION_IDS.AUSTRIA, REGION_IDS.SWITZERLAND]
        .indexOf(this.regionId) >= 0
    },
  },
  async mounted () {
    if (!this.isDeactivatedRegion) {
      regionStore.fetchMemberList(this.groupId)
    }
    try {
      const permissions = await getRegionMemberPermissions(this.groupId)
      this.mayEditMembers = permissions.mayEditMembers
      this.maySetAdminOrAmbassador = permissions.maySetAdminOrAmbassador
      this.mayRemoveAdminOrAmbassador = permissions.mayRemoveAdminOrAmbassador
    } catch (e) {
      pulseError(i18n('error_unexpected'))
    }
    this.isCreatePdf = JSON.parse(localStorage.getItem('regionMemberList_createPdf')) ?? true
    this.isRenewPassport = JSON.parse(localStorage.getItem('regionMemberList_renewPassport')) ?? true
  },
  methods: {
    setPassportSettingsToLocalStorage () {
      localStorage.setItem('regionMemberList_createPdf', this.isCreatePdf)
      localStorage.setItem('regionMemberList_renewPassport', this.isRenewPassport)
    },
    toggleSelectAllTable () {
      this.selectAllTable = !this.selectAllTable

      if (this.selectAllTable) {
        this.passportMember = this.membersFiltered.map(member => member.id)
      } else {
        this.passportMember = []
      }
    },
    passportValidUntilDate (creationDate) {
      const validUntil = new Date(creationDate)
      validUntil.setFullYear(validUntil.getFullYear() + 3)
      return validUntil
    },
    isPassportValid (creationDate) {
      if (creationDate === null) { return true }
      const today = new Date()
      const validUntil = this.passportValidUntilDate(creationDate)
      return today <= validUntil
    },
    isNullOrEmptyOrWhitespace (str) {
      return (str ?? '').trim().length === 0
    },
    canRemoveMember (item) {
      const isNotCurrentUser = this.rowItemNotEqualUserId(this.userId, item.id)
      const isNotAdminOrAmbassador = !this.rowItemIsAdminOrAmbassadorOfRegion(item)

      if (this.isWorkGroup) {
        return isNotCurrentUser && isNotAdminOrAmbassador
      } else {
        return this.activeTab === this.ACTIVE_TAB_DEFAULT && isNotCurrentUser && isNotAdminOrAmbassador
      }
    },
    containsPassportMember (memberId) {
      return this.passportMember.some(member => member === memberId)
    },
    togglePassportMember (itemId) {
      if (!this.containsPassportMember(itemId)) {
        this.passportMember.push(itemId)
      } else {
        this.passportMember.pop(itemId)
      }
    },
    async changeVerification (isVerified, memberId, memberName) {
      const dialogueOptions = {
        title: i18n(isVerified ? 'group.member_list.passports.button.verify' : 'group.member_list.passports.button.unverify'),
        okTitle: i18n('button.yes_i_am_sure'),
        okVariant: isVerified ? 'success' : 'danger',
        params: { name: memberName, id: memberId },
      }
      if (!await this.confirmationDialogue('group.member_list.passports.verify.' + (isVerified ? 'do' : 'undo'), dialogueOptions)) return
      await this.updateVerificationStatusFromUser(isVerified, memberId)
      const index = regionStore.memberList.findIndex(member => member.id === memberId)
      if (index >= 0) {
        regionStore.memberList[index].isVerified = isVerified
      }
    },
    clearFilter () {
      this.filterStatus = null
      this.filterText = ''
    },
    rowItemIsAdminOrAmbassadorOfRegion (value) {
      return value.isAdminOrAmbassadorOfRegion === true
    },
    rowItemNotEqualUserId (user, value) {
      return user !== value
    },
    roleCheckForRegionAndWorkGroup (isGroup, itemRole) {
      return isGroup ? itemRole >= 2 : itemRole === 3
    },
    async degradeAdmin (member) {
      const dialogueOptions = {
        title: i18n(`group.member_list.remove_${this.adminName}_title`),
        okTitle: i18n('yes'),
        params: member,
      }
      if (!await this.confirmationDialogue(`group.member_list.remove_${this.adminName}_text`, dialogueOptions)) return
      showLoader()
      this.isBusy = true
      try {
        await removeAdminOrAmbassador(this.groupId, member.id)
        const index = regionStore.memberList.findIndex(m => m.id === member.id)
        if (index >= 0) {
          regionStore.memberList[index].isAdminOrAmbassadorOfRegion = false
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    async makeAdmin (member) {
      const dialogueOptions = {
        title: i18n(`group.member_list.set_${this.adminName}_title`),
        okTitle: i18n('yes'),
        okVariant: undefined,
        params: member,
      }
      if (!await this.confirmationDialogue(`group.member_list.set_${this.adminName}_text`, dialogueOptions)) return
      showLoader()
      this.isBusy = true
      try {
        await setAdminOrAmbassador(this.groupId, member.id)
        const index = regionStore.memberList.findIndex(m => m.id === member.id)
        if (index >= 0) {
          regionStore.memberList[index].isAdminOrAmbassadorOfRegion = true
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    async removeMember (member) {
      const dialogueOptions = {
        title: i18n('group.member_list.remove_title'),
        okTitle: i18n('yes'),
        params: member,
      }
      if (!await this.confirmationDialogue(`group.member_list.remove_text_${this.isWorkGroup ? 'group' : 'region'}`, dialogueOptions)) return
      showLoader()
      this.isBusy = true
      try {
        await removeMember(this.groupId, member.id)
        const index = regionStore.memberList.findIndex(m => m.id === member.id)
        if (index >= 0) {
          regionStore.memberList.splice(index, 1)
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    containsMember (memberId) {
      return regionStore.memberList.some(member => member.id === memberId)
    },
    async addNewTeamMember (userId) {
      showLoader()
      this.isBusy = true
      try {
        await addMember(this.groupId, userId)

        // the backend doesn't care if the user was already in the group, so we have to check here
        if (!this.containsMember(userId)) {
          await regionStore.fetchMemberList(this.groupId)
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    async updateVerificationStatusFromUser (isVerified, userId) {
      showLoader()
      this.isBusy = true
      try {
        if (isVerified) {
          await verifyUser(userId)
        } else {
          await deverifyUser(userId)
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    clearSelected () {
      this.passportMember = []
    },
    async verifySelectedMember () {
      const dialogueOptions = {
        title: i18n('group.member_list.passports.button.verify'),
        okTitle: i18n('button.yes_i_am_sure'),
        okVariant: 'danger',
      }
      if (!await this.confirmationDialogue('group.member_list.passports.verify.do_selected', dialogueOptions)) return
      try {
        for (const memberId of this.passportMember) {
          const existingMember = regionStore.memberList.find(entry => entry.id === memberId)

          if (!existingMember?.isVerified) {
            await verifyUser(memberId)
          }
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
    },
    async createPassports () {
      showLoader()
      try {
        const response = await createPassportAsAmbassador(this.regionId, this.passportMember, this.isCreatePdf, this.isRenewPassport, this.usePaperSizeDinA4)
        if (this.isCreatePdf) {
          const filename = `fs_passports_${this.regionId}_${this.regionName}.pdf`
          this.downloadFile(response, filename)
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
    downloadFile (blob, filename) {
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    },
  },
}
</script>

<style lang="scss" scoped>
.sleep::after {
  top: -26%;
  left: -9%;
}

.filter-for-label {
  @media (min-width: 375px) {
    flex-basis: 5%;
    order: 1;
    margin-left:1rem;
  }

  @media (min-width: 1200px) {
    flex-basis: 10%;
    order: 1;
    margin-top:0.7rem
  }
}

  .filter-for-form {
  @media (min-width: 375px) {
    flex-basis: 60%;
    order: 2;
    margin-left:1rem;
    margin-top:0.5rem
  }

  @media (min-width: 1200px) {
    flex-basis: 75%;
    order: 2;
    margin:0.5rem
  }
}

  .filter-for-search {
  @media (min-width: 375px) {
    flex-basis: 5%;
    order: 3;
    margin: 0;
  }

  @media (min-width: 1200px) {
    flex-basis: 9%;
    order: 3;
    margin: 0;
  }
}

.user-search-input {
  @media (min-width: 375px) {
    flex-basis: 88%;
    order: 1;
    margin:0.5rem;
    margin-left:1.2rem
  }

  @media (min-width: 1200px) {
    flex-basis: 90%;
    order: 1;
    margin:0.7rem;
    margin-left:1.5rem
  }
}
</style>

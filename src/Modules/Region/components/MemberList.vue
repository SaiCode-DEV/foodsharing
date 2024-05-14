<template>
  <Container :title="title">
    <b-tabs
      v-model="activeTab"
      content-class="mt-2"
      class="p-2"
    >
      <b-tab
        :title="!mayEditMembers ? '' : $i18n('group.member_list.default.title')"
        active
        :disabled="!mayEditMembers"
      >
        <div
          v-if="mayEditMembers"
          class="row p-2"
        >
          <div class="col col-12 col-md-6">
            <user-search-input
              v-if="isWorkGroup"
              id="new-foodsaver-search"
              :placeholder="$i18n('search.user_search.placeholder')"
              button-icon="fa-user-plus"
              :button-tooltip="$i18n('group.member_list.add_member')"
              :filter="notContainsMember"
              @user-selected="addNewTeamMember"
            />
          </div>
          <div class="col col-12 col-md-6">
            <b-form-select
              v-model="filterRole"
              :options="roleOptions"
              size="xl"
            />
          </div>
        </div>
        <div class="row p-2">
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
              max="12"
              size="sm"
              :disabled="!filterLastActivity"
            />
          </div>
        </div>
      </b-tab>
      <b-tab v-if="!isWorkGroup && mayEditMembers" :title="$i18n('group.member_list.passports.title')">
        <b-button
          variant="primary"
          :disabled="!isSelected"
          :class="{'float-right': !viewIsMobile, 'btn-block': viewIsMobile}"
          @click="createPassports"
        >
          {{ $i18n('group.member_list.passports.generate_button') }}
        </b-button>
      </b-tab>
    </b-tabs>
    <b-container>
      <div v-if="memberList.length" class="card-body p-0">
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
          <div class="filter-for-delete">
            <button
              v-b-tooltip.hover
              :title="$i18n('button.clear_filter')"
              type="button"
              class="btn btn-sm"
              @click="clearFilter"
            >
              <i class="fas fa-times" />
            </button>
          </div>
        </div>
      </div>

      <b-table
        :fields="filteredFields"
        :items="membersFilteredSorted"
        :current-page="currentPage"
        :per-page="perPage"
        :sort-compare="compare"
        :busy="isBusy"
        :select-mode="selectMode"
        small
        hover
        responsive
        selectable
        class="foto-table"
        @row-selected="onRowSelected"
      >
        <template #cell(selected)="{ rowSelected }">
          <template v-if="rowSelected">
            <span aria-hidden="true">&check;</span>
            <span class="sr-only">Selected</span>
          </template>
          <template v-else>
            <span aria-hidden="true">&nbsp;</span>
            <span class="sr-only">Not selected</span>
          </template>
        </template>
        <template #cell(imageUrl)="row">
          <Avatar
            :user="row.item"
            :size="50"
          />
        </template>
        <template #cell(userId)="row">
          <a
            :href="$url('profile', row.item.id)"
          >
            {{ row.item.id }}
          </a>
        </template>
        <template #cell(userName)="row">
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
        <template
          v-if="mayRemoveAdminOrAmbassador"
          #cell(removeAdminButton)="row"
        >
          <b-button
            v-if="rowItemisAdminOrAmbassadorOfRegion(row.item)"
            v-b-tooltip="$i18n(isWorkGroup ? 'group.member_list.remove_admin_title' : 'group.member_list.remove_ambassador_title')"
            size="sm"
            variant="danger"
            :disabled="isBusy"
            @click="degradeAdmin(row.item)"
          >
            <i class="fas fa-fw fa-user-slash" />
          </b-button>
        </template>
        <template
          v-if="maySetAdminOrAmbassador"
          #cell(setAdminButton)="row"
        >
          <b-button
            v-if="rowItemNotqualUserid(userId,row.item.id) && roleCheckForRegionAndWorkGroup(isWorkGroup,row.item.role) && !rowItemisAdminOrAmbassadorOfRegion(row.item)"
            v-b-tooltip.left="$i18n(isWorkGroup ? 'group.member_list.set_admin_title' : 'group.member_list.set_ambassador_title')"
            size="sm"
            variant="warning"
            :disabled="isBusy"
            @click="makeAdmin(row.item)"
          >
            <i class="fas fa-fw fa-user-graduate" />
          </b-button>
        </template>
        <template v-if="mayEditMembers" #cell(removeButton)="row">
          <b-button
            v-if="rowItemNotqualUserid(userId,row.item.id) && !rowItemisAdminOrAmbassadorOfRegion(row.item)"
            v-b-tooltip="$i18n('group.member_list.remove_title')"
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
import { optimizedCompare } from '@/utils'
import { addMember } from '@/api/groups'
import { removeMember, setAdminOrAmbassador, removeAdminOrAmbassador } from '@/api/regions'
import RegionsData from '@/stores/regions'
import { hideLoader, pulseError, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import UserSearchInput from '@/components/UserSearchInput'
import { verifyUser, deverifyUser, createPassportAsAmbassador } from '@/api/verification'
import Container from '@/components/Container/Container.vue'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'
import Avatar from '@/components/Avatar/Avatar.vue'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: { UserSearchInput, Container, Avatar },
  mixins: [ConfirmationDialogue, MediaQueryMixin],
  props: {
    userId: { type: Number, default: null },
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
    mayEditMembers: { type: Boolean, default: false },
    maySetAdminOrAmbassador: { type: Boolean, default: false },
    mayRemoveAdminOrAmbassador: { type: Boolean, default: false },
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
      memberList: [],
      isBusy: false,
      roleOptions: [
        { value: null, text: i18n('group.role_name') },
        { value: 1, text: i18n('terminology.role.1') },
        { value: 2, text: i18n('terminology.role.2') },
        { value: 3, text: i18n('terminology.role.3') },
        { value: 4, text: i18n('terminology.role.4') },
      ],
      selectMode: 'multi',
      selected: [],
      activeTab: null,
    }
  },
  computed: {
    isSelected () {
      return this.selected.length > 0
    },
    title () {
      return `${this.isWorkGroup ? this.$i18n('memberlist.header_for_workgroup', { bezirk: this.regionName }) : this.$i18n('memberlist.header_for_district', { bezirk: this.regionName })} ${this.memberCount}`
    },
    memberCount () {
      return this.$i18n('filterlist.some_in_all', { some: this.membersFiltered.length, all: this.memberList.length })
    },
    dateBeforeMonths () {
      return new Date(new Date().getTime() - this.lastActivityFilterMonths * 30 * 24 * 60 * 60 * 1000)
    },
    membersFiltered () {
      if (!this.filterText.trim() && this.filterRole === null && !this.filterLastActivity) {
        return this.memberList
      }
      const filterText = this.filterText ? this.filterText.toLowerCase() : null
      return this.memberList.filter((member) => {
        return (
          ((!filterText ||
            (member.id.toString().startsWith(filterText))) ||
            (member.name.toLowerCase().includes(filterText)) ||
            (this.activeTab === this.ACTIVE_TAB_PASSPORT && member.lastName.toLowerCase().includes(filterText))
          ) &&
          (this.filterRole === null || (member.role === this.filterRole)) &&
          (!this.filterLastActivity || (Date.parse(member.lastActivity) <= this.dateBeforeMonths)) &&
          ((this.activeTab !== this.ACTIVE_TAB_PASSPORT) || (member.isHomeRegion))
        )
      })
    },
    membersFilteredSorted () {
      // sorts the member list alphabetically
      const copy = this.membersFiltered
      return copy.sort(function (a, b) {
        return a.name.localeCompare(b.name)
      })
    },
    filteredFields () {
      const columns = [
        {
          key: 'imageUrl',
          sortable: false,
          label: '',
          class: 'foto-column',
        },
        {
          key: 'userName',
          label: this.$i18n('group.name'),
          sortable: false,
          class: 'align-middle',
        },
      ]

      if (this.activeTab === this.ACTIVE_TAB_PASSPORT) {
        columns.push({
          key: 'lastName',
          label: this.$i18n('group.member_list.default.lastname'),
          sortable: true,
          class: 'align-middle',
        })
      }

      columns.push({
        key: 'userId',
        label: this.$i18n('group.userId'),
        sortable: false,
        class: 'align-middle',
      })

      if (!this.isWorkGroup && this.activeTab === this.ACTIVE_TAB_PASSPORT) {
        columns.push({
          key: 'lastPassDate',
          label: this.$i18n('group.member_list.passports.created_at'),
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

      if (this.activeTab === this.ACTIVE_TAB_DEFAULT) {
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

      if (this.activeTab === this.ACTIVE_TAB_DEFAULT) {
        columns.push(
          {
            key: 'isHomeRegion',
            label: this.$i18n('group.member_list.is_home_region'),
            sortable: true,
            class: 'align-middle',
          },
        )
      }

      if (this.mayEditMembers && this.activeTab === this.ACTIVE_TAB_DEFAULT) {
        columns.push({
          key: 'setAdminButton',
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
  },
  mounted () {
    this.getMemberList()
  },
  methods: {
    async getMemberList () {
      await RegionsData.mutations.fetchMemberList(this.groupId)
      this.memberList = RegionsData.getters.getMemberList(this.groupId)
    },
    async changeVerification (isVerified, memberId, memberName) {
      const dialogueOptions = {
        title: i18n(isVerified ? 'pass.button.verify' : 'pass.button.unverify'),
        okTitle: i18n('button.yes_i_am_sure'),
        okVariant: isVerified ? 'success' : 'danger',
        params: { name: memberName, id: memberId },
      }
      if (!await this.confirmationDialogue('pass.verify.' + (isVerified ? 'do' : 'undo'), dialogueOptions)) return
      await this.updateVerificationStatusFromUser(isVerified, memberId)
      const index = this.memberList.findIndex(member => member.id === memberId)
      if (index >= 0) {
        this.memberList[index].isVerified = isVerified
      }
    },
    compare: optimizedCompare,

    clearFilter () {
      this.filterStatus = null
      this.filterText = ''
    },
    rowItemisAdminOrAmbassadorOfRegion (value) {
      return value.isAdminOrAmbassadorOfRegion === true
    },
    rowItemNotqualUserid (user, value) {
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
        const index = this.memberList.findIndex(m => m.id === member.id)
        if (index >= 0) {
          this.memberList[index].isAdminOrAmbassadorOfRegion = false
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
        const index = this.memberList.findIndex(m => m.id === member.id)
        if (index >= 0) {
          this.memberList[index].isAdminOrAmbassadorOfRegion = true
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
        const index = this.memberList.findIndex(m => m.id === member.id)
        if (index >= 0) {
          this.memberList.splice(index, 1)
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    containsMember (memberId) {
      return this.memberList.some(member => member.id === memberId)
    },
    notContainsMember (memberId) {
      return !this.containsMember(memberId)
    },
    async addNewTeamMember (userId) {
      showLoader()
      this.isBusy = true
      try {
        await addMember(this.groupId, userId)

        // the backend doesn't care if the user was already in the group, so we have to check here
        if (!this.containsMember(userId)) {
          this.getMemberList()
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
    onRowSelected (items) {
      this.selected = items
    },
    async createPassports () {
      const selectedUserIds = this.selected.map(({ id }) => id)
      showLoader()
      try {
        const jsonData = await createPassportAsAmbassador(this.regionId, selectedUserIds)
        const blob = new Blob(jsonData.response, { type: 'application/json' })
        const filename = `fs_passports_${this.regionId}_${this.regionName}.pdf`
        this.downloadFile(blob, filename)
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

  .filter-for-delete {
  @media (min-width: 375px) {
    flex-basis: 2%;
    order: 3;
    margin:0.5rem;
  }

  @media (min-width: 1200px) {
    flex-basis: 5%;
    order: 3;
    margin:0.7rem;
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

<template>
  <div>
    <InaccessibleRegionRedirectWarning />
    <div>
      <div class="d-flex mb-3">
        <SearchBar
          ref="searchBar"
          :query.sync="filterText"
          placeholder="group.filter_placeholder"
        />
        <b-dropdown
          class="ml-2 text-nowrap"
          variant="primary"
          right
        >
          <template #button-content>
            <i class="fas fa-arrow-down-wide-short" />
            <span class="d-none d-md-inline">Sortierung</span>
          </template>
          <b-dropdown-text>
            Sortieren nach:
          </b-dropdown-text>
          <b-dropdown-item
            v-for="key in Object.keys(groupingAndSortingSchemes)"
            :key="key"
            :active="currentScheme === key"
            @click="applyGroupingAndSortingScheme(key)"
          >
            {{ $t('group.sorting_scheme.' + key) }}
          </b-dropdown-item>
        </b-dropdown>
      </div>

      <div v-if="groups === null" class="list-group">
        <div
          v-for="n in 3"
          :key="n"
          class="list-group-item group-entry"
        >
          <div class="align-items-center d-flex">
            <div class="flex-grow-1">
              <b-skeleton width="70%" />
              <b-skeleton
                width="30%"
                height="12px"
                class="mb-0 mt-2"
              />
            </div>
            <div>
              <b-skeleton type="avatar" />
            </div>
          </div>
        </div>
      </div>

      <Container
        v-for="({ header, groups: headerGroups }, i) in groupsByHeader"
        :key="i"
        :hide-header="!header"
        :container-is-expanded="!header?.collapsed"
      >
        <template #title>
          <i class="fas mr-2" :class="header.icon" />
          <h5>
            {{ $t('group.list_headers.' + header.title, { region: currentRegion?.name }) }}
            ({{ headerGroups.length }})
          </h5>
          <span class="flex-grow-1" />
          <Info v-if="header?.infoKey" :info-key="header?.infoKey" />
        </template>
        <template v-for="group in headerGroups">
          <div
            :key="group.id"
            class="list-group-item group-entry"
            :class="{ expanded: expanded === group.id }"
            @click="select(group.id)"
          >
            <div class="align-items-center d-flex">
              <div class="flex-grow-1" :class="{ archived: group.categoryId === GROUP_CATEGORY.ARCHIVED.id}">
                <i class="fas mr-1" :class="groupIcon(group.categoryId)" />
                <a
                  v-if="group.mayAccess"
                  :href="$url('workingGroup', group.id)"
                  @click.stop
                  v-text="group.name"
                />
                <b v-else v-text="group.name" />
                <br>
                <small class="text-muted group-meta-data">
                  <i class="fas fa-user mr-1" /> {{ group.memberCount }}
                  &nbsp;&bull;&nbsp;
                  <span>
                    <i :class="membershipStatus(group).icon + ' fas mr-1'" />
                    {{ membershipStatus(group).text }}
                  </span>
                  <span class="hidden-if-last">
                    &nbsp;&bull;&nbsp;
                  </span>
                </small>
                <b-badge
                  v-if="isInactive(group)"
                  pill
                  variant="danger"
                >
                  <i class="fas fa-hourglass-end mr-1" />
                  {{ $t('group.badge.inactive') }}
                </b-badge>
                <b-badge
                  v-if="group.hasSpecialPermissions"
                  pill
                  variant="info"
                  class="ml-1"
                >
                  <i class="fas fa-key mr-1" />
                  {{ $t('group.badge.special_permissions') }}
                </b-badge>
                <b-badge
                  v-if="group.groupFunctionType"
                  pill
                  variant="info"
                  class="ml-1"
                >
                  <i class="fas fa-key mr-1" />
                  {{ $t('group.badge.function_group') }}
                </b-badge>
              </div>
              <AvatarStack
                v-if="smAndUp"
                :users="group.admins"
              />
              <i class="fas fa-angle-down ml-2" />
            </div>
          </div>
          <div
            v-if="expanded === group.id"
            :key="group.id + '-content'"
            class="list-group-item border-top-0"
          >
            <AvatarStack
              v-if="xs"
              :users="group.admins"
            />
            <div class="d-md-flex">
              <div class="flex-grow-1" :class="{ 'mr-md-3': group.image }">
                <Markdown :source="group.description" class="mb-2" />
                <a :href="$url('mailto_mail_foodsharing_network', group.email)">
                  <i class="fas fa-envelope" />
                  <!-- TODO use internal mail instead if user has one -->
                  {{ group.email }}@foodsharing.network
                </a>
                <b-alert
                  v-if="group.hasSpecialPermissions"
                  variant="info"
                  class="mt-2"
                  show
                >
                  <i class="fas fa-key mr-1" />
                  {{ $t('group.unique_function.tooltip_function_region' + group.id) }}
                </b-alert>

                <b-alert
                  v-if="group.groupFunctionType"
                  variant="info"
                  class="mt-2"
                  show
                >
                  <i class="fas fa-key mr-1" />
                  {{ $t('group.function.function_type_info.' + group.groupFunctionType) }}
                </b-alert>
              </div>
              <div v-if="group.image">
                <img :src="group.image" class="group-image">
              </div>
            </div>

            <div v-if="group.subGroups.length" class="mt-2">
              <h5><a :href="subgroupLink(group)" v-text="$t('group.subgroups')" /></h5>
              <ul>
                <li v-for="subGroup in group.subGroups" :key="subGroup.id">
                  <b v-text="subGroup.name" />
                  <a :href="$url('mailto_mail_foodsharing_network', subGroup.email)"><i class="fas fa-envelope ml-1" /></a>
                  <b-badge
                    v-if="isInactive(subGroup)"
                    pill
                    variant="danger"
                    class="ml-2"
                  >
                    <i class="fas fa-hourglass-end mr-1" />
                    inaktiv
                  </b-badge>
                </li>
              </ul>
            </div>
            <div class="float-right m-2">
              <b-button
                variant="outline-primary"
                @click="groupContactForm?.show()"
              >
                <i class="fas fa-envelope mr-1" />
                {{ $t('group.actions.contact') }}
              </b-button>
              <b-button
                v-if="group.mayJoin"
                variant="outline-primary"
                @click="joinGroup(group.id)"
              >
                <i class="fas fa-user-plus mr-1" />
                {{ $t('group.actions.join') }}
              </b-button>
              <b-button
                v-else-if="group.mayApply"
                variant="outline-primary"
                @click="groupApplicationForm?.show()"
              >
                <i class="fas fa-clipboard-list mr-1" />
                {{ $t('group.actions.apply') }}
              </b-button>

              <b-button
                v-if="group.mayAccess"
                variant="primary"
                :href="$url('workingGroup', group.id)"
              >
                <i class="fas fa-arrow-right-to-bracket mr-1" />
                {{ $t('group.actions.go') }}
              </b-button>
            </div>
          </div>
        </template>
      </Container>
    </div>
    <b-modal
      ref="groupContactForm"
      :title="$t('group.contact.title', {group: currentGroup?.name})"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.send')"
      centered
      :ok-disabled="isMessageOkDisabled"
      @ok.prevent="trySendMail(expanded)"
    >
      <p v-text="$t('group.contact.disclaimer')" />
      <label>{{ $t('terminology.message') }}:</label>
      <b-form-textarea
        id="contactmessage"
        v-model="contactMessage"
        rows="4"
      />
    </b-modal>
    <b-modal
      ref="groupApplicationForm"
      :title="$t('group.application_region', {group: currentGroup?.name})"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.send')"
      centered
      :ok-disabled="isApplicationOkDisabled"
      @ok.prevent="trySendRequest(expanded)"
    >
      <p v-text="$t('group.apply.application_hint', {group: currentGroup?.name})" />
      <b-alert show variant="info">
        <Markdown :source="currentGroup?.applicationPrompt ?? $t('group.apply.default_prompt')" />
      </b-alert>
      <b-form-textarea
        id="input-application"
        v-model="application"
        rows="2"
        class="mt-1"
      />
    </b-modal>
  </div>
</template>

<script setup>
import {
  ref,
  computed,
  getCurrentInstance,
  defineProps,
  onMounted,
  watch,
} from 'vue'
import { addMember, listGroups, sendMail, sendRequest } from '@/api/groups'
import { pulseError, pulseSuccess, shuffle } from '@/script'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user'
import { useRegionStore } from '@/stores/regions'
import Markdown from '@/components/Markdown/Markdown.vue'
import InaccessibleRegionRedirectWarning from '@/components/InaccessibleRegionRedirectWarning.vue'
import AvatarStack from '@/components/Avatar/AvatarStack.vue'
import { useMediaQuery } from '@/composables/useMediaQuery'
import { GROUP_CATEGORY } from '@/stores/groups'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import SearchBar from '@/components/SearchBar/ResultEntry/SearchBar.vue'
import Container from '@/components/Container/Container.vue'
import Info from '@/components/Help/Info.vue'

const props = defineProps({
  regionId: { type: Number, required: true },
})

const { proxy } = getCurrentInstance()
const userStore = useUserStore()
const { xs, smAndUp } = useMediaQuery()
const { confirmationDialogue } = useConfirmationDialogue()
const regionStore = useRegionStore()

// UI state
const filterText = ref('')
const contactMessage = ref('')
const application = ref('')
const expanded = ref(null)
const groups = ref(null)
const groupsByHeader = ref([])
const isLoading = ref(false)
const groupContactForm = ref(null)
const groupApplicationForm = ref(null)

// Derived data
const userId = computed(() => userStore.getUserId)
const currentRegion = computed(() => regionStore.findRegion(props.regionId))
const currentGroup = computed(() => groups.value?.find(x => x.id === expanded.value) || null)
const isApplicationOkDisabled = computed(() => application.value.trim().length === 0 || isLoading.value)
const isMessageOkDisabled = computed(() => contactMessage.value.trim().length === 0 || isLoading.value)

// Lifecycle
onMounted(async () => {
  groups.value = await listGroups(props.regionId)
  groups.value.forEach(group => shuffle(group.admins))
  currentScheme.value = localStorage.getItem('group_list_scheme') || 'name'
  applyGroupingAndSortingScheme(currentScheme.value)

  // Deep-link: if ?search=123 in URL, prefill filter with the name of that group and scroll to the group
  const params = new URLSearchParams(window.location.search)
  const searchParam = params.get('search')
  if (searchParam) {
    const selected = groups.value.find(x => x.id == searchParam) // eslint-disable-line eqeqeq
    if (!selected) {
      filterText.value = searchParam
      return
    }
    filterText.value = selected.name
  }
})

watch(filterText, () => {
  applyGroupingAndSortingScheme(currentScheme.value)
})

// Group state functions
function groupIcon (categoryId) {
  return Object.values(GROUP_CATEGORY).find(x => x.id === categoryId)?.icon || ''
}

function isInactive (group) {
  // half year of inactivity is somewhat arbitrary but seems reasonable to me, especially since currently only forum activity is considered
  return group.latestActivity === null || new Date(group.latestActivity) < new Date(Date.now() - (365 / 2) * 24 * 60 * 60 * 1000)
}

function subgroupLink (group) {
  return group.mayAccess ? proxy.$url('workingGroups', group.id) : null
}

function membershipStatus (group) {
  if (group.hasAppliedFor) {
    return { icon: 'fa-clipboard-question', text: i18n('group.membership_status.applied'), value: 3 }
  } else if (group.mayApply) {
    return { icon: 'fa-clipboard-list', text: i18n('group.membership_status.can_apply'), value: 4 }
  } else if (group.mayJoin) {
    return { icon: 'fa-lock-open', text: i18n('group.membership_status.open'), value: 2 }
  } else if (group.isMember && group.admins.find(x => x.id === userId.value)) {
    return { icon: 'fa-user-cog', text: i18n('group.membership_status.admin'), value: 0 }
  } else if (group.isMember) {
    return { icon: 'fa-user-check', text: i18n('group.membership_status.member'), value: 1 }
  } else {
    return { icon: 'fa-lock', text: i18n('group.membership_status.closed'), value: 5 }
  }
}

// Grouping and Sorting
const groupHeaders = {
  default: { title: 'default', icon: 'fa-users' },
  inactive: { title: 'inactive', icon: 'fa-hourglass-end', infoKey: 'inactive_groups', collapsed: true },
  projectCategory: { title: 'category.project', icon: GROUP_CATEGORY.PROJECT.icon, infoKey: 'group_category' },
  developmentCategory: { title: 'category.development', icon: GROUP_CATEGORY.DEVELOPMENT.icon, infoKey: 'group_category' },
  exchangeCategory: { title: 'category.exchange', icon: GROUP_CATEGORY.EXCHANGE.icon, infoKey: 'group_category' },
  administrativeCategory: { title: 'category.administrative', icon: GROUP_CATEGORY.ADMINISTRATIVE.icon, infoKey: 'group_category' },
  defaultCategory: { title: 'category.default', icon: GROUP_CATEGORY.DEFAULT.icon, infoKey: 'group_category' },
  archivedCategory: { title: 'category.archived', icon: GROUP_CATEGORY.ARCHIVED.icon, infoKey: 'archived_groups', collapsed: true },
  closedMembership: { title: 'membership.closed', icon: 'fa-lock' },
  openMembership: { title: 'membership.open', icon: 'fa-lock-open' },
  canApplyMembership: { title: 'membership.can_apply', icon: 'fa-clipboard-list' },
  appliedMembership: { title: 'membership.applied', icon: 'fa-clipboard-question' },
  memberMembership: { title: 'membership.member', icon: 'fa-user-check' },
  adminMembership: { title: 'membership.admin', icon: 'fa-user-cog' },
  search: { title: 'search_results', icon: 'fa-search' },
}
const groupCategoryOrder = [GROUP_CATEGORY.PROJECT.id, GROUP_CATEGORY.DEVELOPMENT.id, GROUP_CATEGORY.EXCHANGE.id, GROUP_CATEGORY.ADMINISTRATIVE.id, GROUP_CATEGORY.DEFAULT.id, GROUP_CATEGORY.ARCHIVED.id]

const groupingCriteria = {
  inactive: {
    callback: group => isInactive(group) ? 0 : -1,
    headers: [groupHeaders.inactive],
  },
  archived: {
    callback: group => group.categoryId === GROUP_CATEGORY.ARCHIVED.id ? 0 : -1,
    headers: [groupHeaders.archivedCategory],
  },
  category: {
    callback: group => groupCategoryOrder.indexOf(group.categoryId),
    headers: [groupHeaders.projectCategory, groupHeaders.developmentCategory, groupHeaders.exchangeCategory, groupHeaders.administrativeCategory, groupHeaders.defaultCategory, groupHeaders.archivedCategory],
  },
  membership: {
    callback: group => membershipStatus(group).value,
    headers: [groupHeaders.adminMembership, groupHeaders.memberMembership, groupHeaders.openMembership, groupHeaders.appliedMembership, groupHeaders.canApplyMembership, groupHeaders.closedMembership],
  },
}

const sortingCriteria = {
  archived: (a, b) => (a.categoryId === GROUP_CATEGORY.ARCHIVED.id) - (b.categoryId === GROUP_CATEGORY.ARCHIVED.id),
  name: (a, b) => a.name.localeCompare(b.name),
  activity: (a, b) => {
    if (!a.latestActivity && !b.latestActivity) return 0
    if (!a.latestActivity) return 1
    if (!b.latestActivity) return -1
    return new Date(b.latestActivity) - new Date(a.latestActivity)
  },
  membershipStatus: (a, b) => membershipStatus(b).value - membershipStatus(a).value,
  groupCategory: (a, b) => groupCategoryOrder.indexOf(a.categoryId) - groupCategoryOrder.indexOf(b.categoryId),
}

/**
 * sorting and grouping criteria sorted by priority.
 * Groups with the highest priority end up lower.
 */
const groupingAndSortingSchemes = {
  name: {
    grouping: [groupingCriteria.archived],
    sorting: [sortingCriteria.name],
  },
  category: {
    grouping: [groupingCriteria.category],
    sorting: [sortingCriteria.name],
  },
  activity: {
    grouping: [groupingCriteria.archived, groupingCriteria.inactive],
    sorting: [sortingCriteria.activity],
  },
  membership: {
    grouping: [groupingCriteria.archived, groupingCriteria.membership],
    sorting: [sortingCriteria.name],
  },
}
const currentScheme = ref('name')

function groupGroups (groupingScheme) {
  const groupedGroups = []
  const defaultGroup = { header: groupHeaders.default, groups: [...groups.value] }
  for (const groupingCriterion of groupingScheme) {
    const headers = groupingCriterion.headers.map(header => ({ header, groups: [] }))
    const defaultGroups = [...defaultGroup.groups]
    defaultGroup.groups = []
    for (const group of defaultGroups) {
      const headerIndex = groupingCriterion.callback(group)
      const header = headerIndex === -1 ? defaultGroup : headers[headerIndex]
      header.groups.push(group)
    }
    groupedGroups.unshift(...headers.filter(x => x.groups.length > 0))
  }
  if (defaultGroup.groups.length > 0) {
    groupedGroups.unshift(defaultGroup)
  }

  groupsByHeader.value = groupedGroups
}

function sortGroups (sortingScheme) {
  for (const groupList of groupsByHeader.value) {
    groupList.groups.sort((a, b) => {
      for (const criteria of sortingScheme) {
        const result = criteria(a, b)
        if (result !== 0) return result
      }
      return 0
    })
  }
}

function applySearch () {
  if (filterText.value.trim()) {
    const queryWords = filterText.value.toLowerCase().split(/\s+/).filter(Boolean)
    const filteredGroups = groupsByHeader.value.flatMap(x => x.groups).filter(group =>
      queryWords.every(word => group.name.toLowerCase().includes(word)),
    )
    const searchGroup = { header: groupHeaders.search, groups: filteredGroups }
    groupsByHeader.value.splice(0, groupsByHeader.value.length, searchGroup)
    if (filteredGroups.length === 1) {
      expanded.value = filteredGroups[0].id
    } else {
      expanded.value = null
    }
  }
}

function applyGroupingAndSortingScheme (schemeKey) {
  const scheme = groupingAndSortingSchemes[schemeKey]
  groupGroups(scheme.grouping)
  sortGroups(scheme.sorting)
  applySearch()
  currentScheme.value = schemeKey
  localStorage.setItem('group_list_scheme', schemeKey)
}

// Group actions
function select (id) {
  if (expanded.value !== id) {
    expanded.value = id
  } else {
    expanded.value = null
  }
}

async function trySendMail (groupId) {
  try {
    isLoading.value = true
    await sendMail(groupId, contactMessage.value)
    groupContactForm.value?.hide()
    contactMessage.value = ''
    pulseSuccess(i18n('success'))
  } catch (err) {
    pulseError(`${i18n('error_unexpected')}<br><br> ${err.message}`)
  } finally {
    isLoading.value = false
  }
}

async function trySendRequest (groupId) {
  try {
    isLoading.value = true
    await sendRequest(groupId, application.value)
    pulseSuccess(i18n('success'))
    groupApplicationForm.value?.hide()
    application.value = ''
    currentGroup.value.hasAppliedFor = true
    currentGroup.value.mayApply = false
    if (currentScheme.value === 'membership') {
      applyGroupingAndSortingScheme(currentScheme.value)
    }
  } catch (err) {
    pulseError(`${i18n('error_unexpected')}<br><br> ${err.message}`)
  } finally {
    isLoading.value = false
  }
}

async function joinGroup (groupId) {
  if (!await confirmationDialogue('group.join_confirmation', {
    okTitle: i18n('yes'),
    okVariant: 'primary',
    params: { name: currentGroup.value?.name },
  })) return
  try {
    await addMember(groupId, userId.value)
    window.location.href = proxy.$url('relogin_and_redirect_to_url', proxy.$url('workingGroup', groupId))
  } catch (e) {
    pulseError(`${i18n('error_unexpected')}<br><br> ${e.message}`)
  }
}
</script>
<style scoped>
.group-image {
  min-width: 15em;
  max-width: 100%;
  border: 1px solid var(--fs-border-default);
  border-radius: var(--border-radius);
}

.list-group-item:not(:last-child) {
  border-bottom: 0 !important;
}

.group-entry {
  background-color: rgb(250, 250, 250);
  &:hover, &.expanded {
    background-color: var(--fs-color-elevated) !important;
  }
}

.expanded .fa-angle-down {
  transform: rotate(180deg);
}

.archived {
  opacity: 0.5;
}

.group-meta-data:last-child > .hidden-if-last {
  display: none;
}
</style>

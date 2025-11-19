<template>
  <div>
    <InaccessibleRegionRedirectWarning />
    <b-tabs content-class="mt-3">
      <b-tab
        :title="isGlobalWorkingGroup ? $t('sidenav.superregional') : $t('sidenav.localgroups')"
        active
      >
        <input
          v-model="filterText"
          class="mb-3"
          type="text"
          :placeholder="$t('group.filter_placeholder')"
        >
        <Container
          v-for="group in filteredGroups"
          :id="'group-' + group.id"
          :key="group.id"
          :container-is-expanded="isContainerExpanded"
          :title="group.name"
          :tooltip-key="group.function_tooltip_key ? $t(group.function_tooltip_key) : null"
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
                  class="mr-2 mb-2"
                />
                <div class="mt-2">
                  <strong>{{ translateCounted('group.admin_count', group.leaders.length) }}</strong>
                  <p>{{ translateCounted('group.member_count', group.membersCount) }}</p>
                  <Markdown :source="group.teaser" />
                  <div class="mt-2">
                    <a :href="$url('mailto_mail', group.email)">{{ group.email }}</a>
                  </div>
                </div>
              </b-col>
              <b-col>
                <img
                  v-if="group.image"
                  :src="group.image"
                  class="group-image"
                >
              </b-col>
            </b-row>
            <div class="float-right m-2">
              <b-button
                variant="primary"
                @click="openContactModal(group)"
              >
                {{ $t('group.actions.contact') }}
              </b-button>
              <b-button
                v-if="group.mayEdit"
                variant="primary"
                :href="$url('workingGroupEdit', group.id)"
              >
                {{ $t('group.actions.edit') }}
              </b-button>
              <b-button
                v-if="group.mayAccess"
                variant="primary"
                :href="$url('workingGroup', group.id)"
              >
                {{ $t('group.actions.go') }}
              </b-button>
              <b-button
                v-if="group.mayJoin"
                variant="primary"
                @click="joinGroup(group.id)"
              >
                {{ $t('group.actions.join') }}
              </b-button>
              <b-button
                v-else-if="group.mayApply"
                variant="primary"
                @click="openRequestModal(group)"
              >
                {{ $t('group.actions.apply') }}
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
      :title="$t('group.contact.title', {group: selectedGroupName})"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.send')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="trySendMail(selectedGroupId)"
    >
      <p>
        {{ $t('group.contact.disclaimer') }}
      </p>
      <label>{{ $t('terminology.message') }}:</label>
      <b-form-textarea
        id="contactmessage"
        v-model="contactMessage"
        rows="4"
      />
    </b-modal>
    <b-modal
      ref="groupRequestForm"
      :title="$t('group.application_region', {group: selectedGroupName})"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.send')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok.prevent="trySendRequest(selectedGroupId)"
    >
      <p>
        <label>{{ $t('group.apply.motivation', { group: selectedGroupName }) }}</label>
        <b-form-textarea
          id="input-motivation"
          v-model="motivation"
          rows="2"
          class="mt-1"
        />
      </p>
      <p>
        <label>{{ $t('group.apply.ability') }}</label>
        <b-form-textarea
          id="input-ability"
          v-model="ability"
          rows="2"
          class="mt-1"
        />
      </p>
      <p>
        <label>{{ $t('group.apply.experience') }}</label>
        <b-form-textarea
          id="input-experience"
          v-model="experience"
          rows="2"
          class="mt-1"
        />
      </p>
      <p>
        <label>{{ $t('group.apply.how_much_time') }}</label>
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

<script setup>
import {
  ref,
  computed,
  getCurrentInstance,
  defineProps,
  nextTick,
  onMounted,
  watch,
} from 'vue'
import Container from '@/components/Container/Container.vue'
import Avatar from '@/components/Avatar/Avatar.vue'
import { addMember, sendMail, sendRequest } from '@/api/groups'
import { pulseError, pulseSuccess } from '@/script'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user'
import Markdown from '@/components/Markdown/Markdown.vue'
import InaccessibleRegionRedirectWarning from '@/components/InaccessibleRegionRedirectWarning.vue'

const props = defineProps({
  groups: { type: Array, required: true },
  nav: { type: Object, required: true },
  isGlobalWorkingGroup: { type: Boolean, required: true },
})

const { proxy } = getCurrentInstance()

const userStore = useUserStore()

// UI state
const isContainerExpanded = ref(false)
const filterText = ref('')
const contactMessage = ref('')
const selectedGroupId = ref(null)
const selectedGroupName = ref('')
const motivation = ref('')
const ability = ref('')
const experience = ref('')
const selectedTime = ref(null)

const groupContactForm = ref(null)
const groupRequestForm = ref(null)

// Derived data
const timeOptions = computed(() => [1, 2, 3, 5].map(i => ({ value: i, text: i18n(`group.apply.time.${i}`) })))

const navItems = computed(() => ([
  { title: i18n('sidenav.yourregions'), items: props.nav.local },
  { title: i18n('sidenav.yourgroups'), items: props.nav.groups },
]))

const userId = computed(() => userStore.getUserId)

const filteredGroups = computed(() =>
  props.groups.filter(group =>
    group.name.toLowerCase().includes((filterText.value || '').toLowerCase()) ||
    group.id.toString().includes((filterText.value || '').toLowerCase()),
  ),
)

// helpers
function getPluralKey (count) {
  if (count === 0) return 'zero'
  if (count === 1) return 'one'
  return 'other'
}

function translateCounted (key, count) {
  const translationKey = key + '.' + getPluralKey(count)
  return i18n(translationKey, { count })
}

// actions
function openContactModal (group) {
  selectedGroupId.value = group.id
  selectedGroupName.value = group.name
  groupContactForm.value?.show()
}

function openRequestModal (group) {
  selectedGroupId.value = group.id
  selectedGroupName.value = group.name
  groupRequestForm.value?.show()
}

async function trySendMail (groupId) {
  try {
    await sendMail(groupId, contactMessage.value)
    pulseSuccess(i18n('success'))
  } catch (err) {
    pulseError(`${i18n('error_unexpected')}<br><br> ${err.message}`)
  }
}

async function trySendRequest (groupId) {
  try {
    if (selectedTime.value === null) {
      pulseError(i18n('group.apply.error_missing_time'))
      return
    }
    await sendRequest(groupId, motivation.value, ability.value, experience.value, selectedTime.value)
    pulseSuccess(i18n('success'))
    groupRequestForm.value?.hide()
  } catch (err) {
    pulseError(`${i18n('error_unexpected')}<br><br> ${err.message}`)
  }
}

async function joinGroup (groupId) {
  try {
    await addMember(groupId, userId.value)
    window.location.href = proxy.$url('relogin_and_redirect_to_url', proxy.$url('workingGroup', groupId))
  } catch (e) {
    pulseError(`${i18n('error_unexpected')}<br><br> ${e.message}`)
  }
}

// Deep-link: if ?id=123 in URL, prefill filter with that id and scroll to the group
onMounted(async () => {
  const params = new URLSearchParams(window.location.search)
  const searchParam = params.get('search')
  if (searchParam) {
    filterText.value = searchParam
    await nextTick()
    const el = document.getElementById(`group-${searchParam}`)
    if (el) {
      const top = el.getBoundingClientRect().top + window.pageYOffset
      window.scrollTo({ top: Math.max(0, top - 150), behavior: 'smooth' })
    }
  }
})

// Auto-expand when exactly one group is visible
watch(filteredGroups, (list) => {
  isContainerExpanded.value = list.length === 1
}, { immediate: true })
</script>
<style scoped>
.group-image {
  min-width: 15em;
  max-width: 100%;
}
</style>

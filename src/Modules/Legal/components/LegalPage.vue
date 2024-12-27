<template>
  <div>
    <Container
      :title="$i18n('legal.privacy_policy')"
      :wrap-content="true"
      :collapsible="false"
    >
      <Container
        :hide-header="true"
        :wrap-content="true"
        :collapsible="false"
        :max-height="400"
        :enable-scroll="true"
      >
        <!-- eslint-disable vue/no-v-html -->
        <!-- Sanitized in Modules/Content/ContentGateway.php get() -->
        <div v-html="privacyPolicyContent" />
        <!-- eslint-enable -->
      </Container>
      <Container
        v-if="showPrivacyNotice"
        :title="$i18n('legal.privacy_notice')"
        :collapsible="false"
        :wrap-content="true"
      >
        <!-- eslint-disable vue/no-v-html -->
        <!-- Sanitized in Modules/Content/ContentGateway.php get() -->
        <div v-html="privacyNoticeContent" />
      <!-- eslint-enable -->
      </Container>

      <div
        v-if="userStore.isLoggedIn"
      >
        <b-card bg-variant="light" class="border-0 mb-4">
          <b-form-group :label="$i18n('legal.acknowledge.qustion_policy')" label-class="font-weight-bold">
            <b-form-select v-model="acknowledgedData" :options="noticeOptions" />
          </b-form-group>
          <h4 v-if="acknowledgedData === 'agree_only_policy'" class="mb-4 text-danger">
            {{ $i18n('legal.not_acknowledge_privacy_notice_description') }}
          </h4>

          <h4 v-if="acknowledgedData === 'not_agree'" class="mb-4 text-danger">
            {{ $i18n('legal.not_acknowledge_privacy_policy_description') }}
          </h4>
          <b-button
            v-if="acknowledgeButtonText"
            :variant="acknowledgeButtonColor"
            @click="updateAcknowledge"
          >
            {{ acknowledgeButtonText }}
          </b-button>
        </b-card>
      </div>
    </Container>
    <b-modal
      v-if="acknowledgedData === 'agree_only_policy'"
      id="modalFoodsaverAccount"
      ref="modalFoodsaverAccount"
      v-model="modalFoodsaverVisible"
      centered
      no-close-on-backdrop
      size="lg"
      :visible="true"
      :title="$i18n('legal.button.partially_agree')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('legal.button.partially_agree')"
      ok-variant="danger"
      cancel-variant="success"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="makeFoodsaverAccount"
      @hidden="hideLoader()"
    >
      <b-alert show variant="danger">
        <h2>
          {{ $i18n('legal.are_you_sure_to_downgrade') }}
        </h2>
      </b-alert>
    </b-modal>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue'
import Container from '@/components/Container/Container.vue'
import { useUserStore } from '@/stores/user.js'
import i18n from '@/helper/i18n'
import { goTo, hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import { updateLegalAcknowledge, getLegalPageData } from '@/api/legal'
import { CONTENT_IDS, getContent } from '@/api/content'
import { url } from '@/helper/urls'

const userStore = useUserStore()

const modalFoodsaverVisible = ref(false)
const acknowledgedData = ref(null)
const showPrivacyNotice = ref(false)
const privacyPolicyAcknowledged = ref(false)
const privacyNoticeAcknowledged = ref(false)

onMounted(() => {
  fetchLegalPageData()
  getLegalContent()
})

// if !showPrivacyNotice, legal.acknowledge.agree/not_agree.
// if showPrivacyNotice, legal.acknowledge.agree_only_policy/agree_policy_and_notice/not_agree
const noticeOptions = computed(() => {
  if (!showPrivacyNotice.value) {
    return [
      { value: null, text: i18n('legal.acknowledge.select'), disabled: true },
      { value: 'agree', text: i18n('legal.acknowledge.agree') },
      { value: 'not_agree', text: i18n('legal.acknowledge.not_agree') },
    ]
  }
  return [
    { value: null, text: i18n('legal.acknowledge.select'), disabled: true },
    { value: 'agree_policy_and_notice', text: i18n('legal.acknowledge.agree_policy_and_notice') },
    { value: 'agree_only_policy', text: i18n('legal.acknowledge.agree_only_policy') },
    { value: 'not_agree', text: i18n('legal.acknowledge.not_agree') },
  ]
})

const acknowledgeButtonText = computed(() => {
  switch (acknowledgedData.value) {
    case 'agree':
    case 'agree_policy_and_notice':
      return i18n('legal.button.agree')
    case 'agree_only_policy':
      return i18n('legal.button.partially_agree')
    case 'not_agree':
      return i18n('legal.button.disagree')
    case null:
    default:
      return ''
  }
})

const acknowledgeButtonColor = computed(() => {
  switch (acknowledgedData.value) {
    case 'agree':
    case 'agree_policy_and_notice':
      return 'success'
    case 'agree_only_policy':
    case 'not_agree':
      return 'danger'
    case null:
    default:
      return 'secondary'
  }
})

const privacyPolicyContent = ref(null)
const privacyNoticeContent = ref(null)

async function fetchLegalPageData () {
  getLegalPageData()
    .then((response) => {
      showPrivacyNotice.value = response.showPrivacyNotice
      privacyPolicyAcknowledged.value = response.privacyPolicyAcknowledged
      privacyNoticeAcknowledged.value = response.privacyNoticeAcknowledged
    })
    .catch(() => {
      showPrivacyNotice.value = false
      privacyPolicyAcknowledged.value = false
      privacyNoticeAcknowledged.value = false
    })
}

async function getLegalContent () {
  getContent(CONTENT_IDS.PRIVACY_POLICY_CONTENT)
    .then((content) => {
      privacyPolicyContent.value = content.body
    })
    .catch(() => {
      privacyPolicyContent.value = null
    })

  getContent(CONTENT_IDS.PRIVACY_NOTICE_CONTENT)
    .then((content) => {
      privacyNoticeContent.value = content.body
    })
    .catch(() => {
      privacyNoticeContent.value = null
    })
}

async function updateAcknowledge () {
  showLoader()
  if (acknowledgedData.value === 'not_agree') {
    goTo(url('settingsDeleteAccount'))
    return
  }
  if (acknowledgedData.value === 'agree_only_policy') {
    modalFoodsaverVisible.value = true
    hideLoader()
    return
  }
  try {
    const privacyPolicy = acknowledgedData.value === 'agree_policy_and_notice' || acknowledgedData.value === 'agree'
    const privacyNotice = acknowledgedData.value === 'agree_policy_and_notice' || null
    await updateLegalAcknowledge(privacyPolicy, privacyNotice)
    pulseSuccess(i18n('legal.success'))
    goTo(url('dashboard'))
  } catch (error) {
    console.error('updateAcknowledge', error)
    pulseError(i18n('error_unexpected'))
  } finally {
    hideLoader()
  }
}

async function makeFoodsaverAccount () {
  if (!acknowledgedData.value === 'agree_only_policy') {
    return
  }
  showLoader()
  try {
    await updateLegalAcknowledge(true, false)
    pulseSuccess(i18n('legal.success'))
    goTo(url('dashboard'))
  } catch (error) {
    console.error('updateAcknowledge', error)
    pulseError(i18n('error_unexpected'))
  } finally {
    hideLoader()
  }
}

</script>

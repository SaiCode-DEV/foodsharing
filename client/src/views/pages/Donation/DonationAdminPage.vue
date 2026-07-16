<template>
  <Container :collapsible="false" wrap-content>
    <template #title>
      {{ $t('donation_admin_page.title') }}
    </template>

    <b-row class="mb-3">
      <b-col
        v-for="(urlField, idx) in urlFields"
        :key="urlField.key || idx"
        cols="12"
        class="mb-2"
      >
        <b-form-group :label="urlField.label" label-class="small mb-1">
          <b-input-group>
            <b-form-input
              v-model="urlField.model.value"
              :placeholder="urlField.label"
              type="url"
              autocomplete="off"
            />
            <template #append>
              <b-button
                variant="outline-danger"
                size="sm"
                :title="$t('donation_admin_page.delete_url')"
                @click="deleteUrl(urlField)"
              >
                <i class="fas fa-trash" aria-hidden="true" />
              </b-button>
            </template>
          </b-input-group>
        </b-form-group>
      </b-col>
    </b-row>

    <b-row class="mb-3">
      <b-col
        v-for="projectField in projectFields"
        :key="projectField.key"
        cols="12"
        md="3"
        class="mb-2"
      >
        <b-form-group :label="projectField.label" label-class="small d-block">
          <b-form-select
            v-model="projectField.model.value"
            :options="donationProjectOptions"
            :placeholder="$t('donation_admin_page.select_project')"
            :disabled="donationProjectOptions.length === 0"
          />
        </b-form-group>
      </b-col>
    </b-row>

    <b-row class="mb-3">
      <b-col>
        <b-form-checkbox
          v-model="showCampaignCard"
          switch
          class="m-0 w-100"
        >
          {{ i18n('donation_admin_page.show_campaign_card') }}
        </b-form-checkbox>
        <div v-if="showCampaignCard" class="ml-4 mt-2">
          <span v-for="(donationCampaignBoolean, idx) in donationCampaignBooleans" :key="donationCampaignBoolean.key || idx">
            <b-form-checkbox v-model="donationCampaignBoolean.model.value" class="m-0 w-100">
              {{ donationCampaignBoolean.label }}
            </b-form-checkbox>
          </span>
        </div>
      </b-col>
      <b-col
        cols="12"
        md="6"
        class="mb-2 d-flex align-items-center"
      >
        <b-row>
          <b-col>
            <b-form-checkbox
              v-model="showDonationModal"
              switch
              class="m-0 w-100"
            >
              {{ i18n('donation_admin_page.show_donation_modal') }}
            </b-form-checkbox>
            <b-row v-if="showDonationModal" class="mt-2">
              <b-col cols="6">
                <b-form-group :label="$t('donation_admin_page.show_donation_modal_in_hours_for_logged_in_users')" label-class="small d-block">
                  <b-form-input
                    v-model="showDonationModalInHoursForLoggedInUsers"
                    type="number"
                    min="0"
                    step="1"
                  />
                </b-form-group>
              </b-col>
              <b-col cols="6">
                <b-form-group :label="$t('donation_admin_page.show_donation_modal_in_hours_for_logged_out_users')" label-class="small d-block">
                  <b-form-input
                    v-model="showDonationModalInHoursForLoggedOutUsers"
                    type="number"
                    min="0"
                    step="1"
                  />
                </b-form-group>
              </b-col>
            </b-row>
          </b-col>
        </b-row>
      </b-col>
    </b-row>

    <b-button variant="primary" @click="saveDonationData">
      {{ $t('globals.save') }}
    </b-button>
  </Container>
</template>

<script setup>
import Container from '@/components/Container/Container.vue'
import { ref, computed, onMounted, watch } from 'vue'
import { updateDonationData, getDonationProjects } from '@/api/donation'
import { useDonationStore } from '@/stores/donation'
import i18n from '@/helper/i18n'
import { pulseError, pulseSuccess } from '@/script'

function deleteUrl (urlField) {
  if (urlField && urlField.model) {
    urlField.model.value = ''
  }
}

const campaignActive = ref(false)
const iframeCampaignUrl = ref('')
const iframeFriendshipCircleUrl = ref('')
const iframeOneTimeUrl = ref('')
const iframeSelfserviceUrl = ref('')
const donationModalInfoUrl = ref('')
const donationModalPopupUrl = ref('')

const campaignId = ref(0)
const friendshipCircleId = ref(0)
const donationModalId = ref(0)
const oneTimeDonationId = ref(0)
const showCampaignCard = ref(false)
const showDonationModal = ref(false)
const showDonationModalInHoursForLoggedInUsers = ref(0)
const showDonationModalInHoursForLoggedOutUsers = ref(0)
const showDonationCampaignPart1 = ref(false)
const showDonationCampaignPart2 = ref(false)
const showDonationCampaignGallery = ref(false)

const urlFields = [
  { key: 'iframeCampaignUrl', label: i18n('donation_admin_page.iframe_campaign_url'), model: iframeCampaignUrl },
  { key: 'iframeFriendshipCircleUrl', label: i18n('donation_admin_page.iframe_friendship_circle_url'), model: iframeFriendshipCircleUrl },
  { key: 'iframeOneTimeUrl', label: i18n('donation_admin_page.iframe_one_time_url'), model: iframeOneTimeUrl },
  { key: 'iframeSelfserviceUrl', label: i18n('donation_admin_page.iframe_selfservice_url'), model: iframeSelfserviceUrl },
  { key: 'donationModalInfoUrl', label: i18n('donation_admin_page.donation_modal_info_url'), model: donationModalInfoUrl },
  { key: 'donationModalPopupUrl', label: i18n('donation_admin_page.donation_modal_popup_url'), model: donationModalPopupUrl },
]

const projectFields = [
  { key: 'campaignId', label: i18n('donation_admin_page.campaign_project'), model: campaignId },
  { key: 'friendshipCircleId', label: i18n('donation_admin_page.friendship_circle_project'), model: friendshipCircleId },
  { key: 'donationModalId', label: i18n('donation_admin_page.donation_modal_project'), model: donationModalId },
  { key: 'oneTimeDonationId', label: i18n('donation_admin_page.one_time_donation_project'), model: oneTimeDonationId },
]

const donationStore = useDonationStore()

const donationProjects = ref([])

const donationProjectOptions = computed(() =>
  donationProjects.value.map(project => ({
    value: project.id,
    text: project.name,
  })),
)

onMounted(async () => {
  try {
    donationProjects.value = await getDonationProjects()
  } catch (e) {
    console.error(i18n('donation_admin_page.error_loading_projects'), e)
  }

  watch(
    () => donationStore.donationData,
    (newVal) => {
      if (donationProjects.value.length > 0) {
        assignDonationData(newVal)
      }
    },
    { immediate: true },
  )
})

const donationCampaignBooleans = [
  { key: 'showDonationCampaignPart1', label: i18n('donation_admin_page.show_donation_campaign_part_1'), model: showDonationCampaignPart1 },
  { key: 'showDonationCampaignPart2', label: i18n('donation_admin_page.show_donation_campaign_part_2'), model: showDonationCampaignPart2 },
  { key: 'showDonationCampaignGallery', label: i18n('donation_admin_page.show_donation_campaign_gallery'), model: showDonationCampaignGallery },
]

function assignDonationData (data) {
  campaignActive.value = data.campaignActive ?? false
  iframeCampaignUrl.value = data.iframeCampaignUrl ?? ''
  iframeFriendshipCircleUrl.value = data.iframeFriendshipCircleUrl ?? ''
  iframeOneTimeUrl.value = data.iframeOneTimeUrl ?? ''
  iframeSelfserviceUrl.value = data.iframeSelfserviceUrl ?? ''
  donationModalInfoUrl.value = data.donationModalInfoUrl ?? ''
  donationModalPopupUrl.value = data.donationModalPopupUrl ?? ''
  campaignId.value = data.campaignId ?? 0
  friendshipCircleId.value = data.friendshipCircleId ?? 0
  donationModalId.value = data.donationModalId ?? 0
  oneTimeDonationId.value = data.oneTimeDonationId ?? 0
  showCampaignCard.value = data.showCampaignCard ?? false
  showDonationModal.value = data.showDonationModal ?? false
  showDonationModalInHoursForLoggedInUsers.value = data.showDonationModalInHoursForLoggedInUsers ?? 0
  showDonationModalInHoursForLoggedOutUsers.value = data.showDonationModalInHoursForLoggedOutUsers ?? 0
  showDonationCampaignPart1.value = data.showDonationCampaignPart1 ?? false
  showDonationCampaignPart2.value = data.showDonationCampaignPart2 ?? false
  showDonationCampaignGallery.value = data.showDonationCampaignGallery ?? false
}

function saveDonationData () {
  console.log('saving DonationData')
  const donationData = {
    campaignId: Number(campaignId.value || 0),
    friendshipCircleId: Number(friendshipCircleId.value || 0),
    donationModalId: Number(donationModalId.value || 0),
    oneTimeDonationId: Number(oneTimeDonationId.value || 0),
    showCampaignCard: Boolean(showCampaignCard.value),
    showDonationModal: Boolean(showDonationModal.value),
    showDonationModalInHoursForLoggedInUsers: Number(showDonationModalInHoursForLoggedInUsers.value || 0),
    showDonationModalInHoursForLoggedOutUsers: Number(showDonationModalInHoursForLoggedOutUsers.value || 0),
    showDonationCampaignPart1: Boolean(showDonationCampaignPart1.value),
    showDonationCampaignPart2: Boolean(showDonationCampaignPart2.value),
    showDonationCampaignGallery: Boolean(showDonationCampaignGallery.value),
    donationModalInfoUrl: donationModalInfoUrl.value || '',
    donationModalPopupUrl: donationModalPopupUrl.value || '',
    iframeFriendshipCircleUrl: iframeFriendshipCircleUrl.value || '',
    iframeOneTimeUrl: iframeOneTimeUrl.value || '',
    iframeCampaignUrl: iframeCampaignUrl.value || '',
    iframeSelfserviceUrl: iframeSelfserviceUrl.value || '',
  }
  try {
    updateDonationData(donationData)
    pulseSuccess(i18n('donation_admin_page.save_success'))
  } catch (error) {
    pulseError(i18n('donation_admin_page.save_error'))
    console.error('Error saving donation data:', error)
  }
}
</script>

<style scoped>
.donation-admin-page {
  max-width: 500px;
  margin: 2rem auto;
  padding: 2rem;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
label {
  display: block;
  margin-bottom: 1rem;
}
input[type="text"] {
  width: 100%;
  padding: 0.5rem;
  margin-top: 0.5rem;
  box-sizing: border-box;
}
.spendenbanner-preview {
  margin-top: 2rem;
  padding: 1rem;
  background: #f7f7f7;
  border-radius: 6px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
</style>

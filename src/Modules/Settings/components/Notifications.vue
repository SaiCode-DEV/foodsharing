<template>
  <div>
    <div class="pt-2">
      <div>
        <h4>{{ $t('notifications.chat.title') }}</h4>
        <b-row>
          <b-col
            cols="12"
            lg="5"
          >
            {{ $t('notifications.chat.description') }}
          </b-col>
          <b-col
            cols="4"
            lg="2"
            class="pt-1"
          >
            <b-form-checkbox
              id="infomail_message"
              v-model="infoMailState"
              size="sm"
            >
              {{ $t('notifications.checkbox_email') }}
            </b-form-checkbox>
          </b-col>
          <b-col
            cols="4"
            lg="3"
            class="pt-1"
          >
            <LoadingOverlay :active="pushNotificationsLoading" rounded="sm">
              <b-form-checkbox
                v-if="mayUsePushNotifications || pushNotificationsLoading"
                :checked="usePushNotifications"
                :disabled="pushNotificationsLoading"
                size="sm"
                @change="updatePushNotifications"
              >
                {{ $t('notifications.checkbox_push') }}
              </b-form-checkbox>
            </LoadingOverlay>
          </b-col>
          <b-alert
            v-if="isSafari"
            show
            variant="info"
            class="mx-3 mt-2"
          >
            <!-- eslint-disable-next-line vue/no-v-html -->
            <Markdown :source="$t('notifications.safari_add_to_home_screen', {icon: safariShareIcon})" />
          </b-alert>
        </b-row>
      </div>
    </div>

    <div class="pt-2">
      <h4>{{ $t('notifications.foodSharePoints.title') }}</h4>
      <b-row>
        <b-col
          cols="12"
          lg="5"
        >
          {{ $t('notifications.foodSharePoints.description') }}
          <div>
            <b-button
              class="mt-2"
              size="sm"
              variant="outline-primary"
              :disabled="currentFoodSharePoints.length <= 0"
              @click="toogleFoodSharePointDetails"
            >
              {{ $t('notifications.config_button') }}
            </b-button>
          </div>
        </b-col>
        <b-col
          cols="4"
          lg="2"
          class="pt-1"
        >
          <b-form-checkbox
            v-model="isFoodSharePointGlobalEmailNotificationActive"
            size="sm"
            @change="toggleGlobalNotification('currentFoodSharePoints', 'infotype', Number(isFoodSharePointGlobalEmailNotificationActive))"
          >
            {{ $t('notifications.checkbox_email') }}
          </b-form-checkbox>
        </b-col>
        <b-col
          cols="4"
          lg="3"
          class="pt-1"
        >
          <b-form-checkbox
            v-model="isFoodSharePointGlobalBellNotificationActive"
            size="sm"
            @change="toggleGlobalNotification('currentFoodSharePoints', 'infotype', toggleFoodSharePointBell(isFoodSharePointGlobalBellNotificationActive))"
          >
            {{ $t('notifications.checkbox_bell') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <div v-if="editFoodSharePointNotification">
      <div
        v-for="foodSharePoint in currentFoodSharePoints"
        :key="foodSharePoint.id"
        class="pb-2 pt-2"
      >
        <b-row align-v="center">
          <b-col cols="12" lg="6">
            {{ foodSharePoint.name }}
          </b-col>
          <b-col cols="12" lg="6">
            <b-form-checkbox
              v-model="foodSharePoint.bell"
              size="sm"
              class="d-inline-block"
              @change="bell => foodSharePoint.email &&= bell"
            >
              {{ $t('notifications.checkbox_bell') }}
            </b-form-checkbox>
            <b-form-checkbox
              v-model="foodSharePoint.email"
              size="sm"
              class="d-inline-block mr-2"
              @change="email => foodSharePoint.bell ||= email"
            >
              {{ $t('notifications.checkbox_email') }}
            </b-form-checkbox>
          </b-col>
        </b-row>
        <hr class="my-2"> <!-- Linie -->
      </div>
    </div>

    <div class="pt-2">
      <h4>{{ $t('notifications.threads.title') }}</h4>
      <b-row>
        <b-col
          cols="6"
          lg="5"
        >
          <div>
            <b-button
              class="mt-2"
              size="sm"
              variant="outline-primary"
              :disabled="currentThreads.length <= 0"
              @click="toogleThreadsDetails"
            >
              {{ $t('notifications.config_button') }}
            </b-button>
          </div>
        </b-col>
        <b-col cols="6" lg="2">
          <b-form-checkbox
            v-model="isThreadsGlobalEmailNotificationActive"
            size="sm"
            @change="toggleGlobalNotification('currentThreads', 'infotype', Number(isThreadsGlobalEmailNotificationActive))"
          >
            {{ $t('notifications.checkbox_email') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <div v-if="editThreadsNotification">
      <div
        v-for="thread in currentThreads"
        :key="thread.id"
        class="pb-2 pt-2"
      >
        <b-row align-v="center">
          <b-col cols="12" lg="6">
            {{ thread.region.name }} / {{ thread.name }}
          </b-col>
          <b-col cols="12" lg="6">
            <b-form-radio-group
              v-model="thread.email"
              :options="emailNotificationOptions"
              :name="'radio-button-' + thread.id"
            />
          </b-col>
        </b-row>
        <hr class="my-2"> <!-- Linie -->
      </div>
    </div>

    <div class="pt-2">
      <h4>{{ $t('notifications.regions.title') }}</h4>
      <b-row>
        <b-col
          cols="6"
          lg="5"
        >
          <div>
            <b-button
              class="mt-2"
              size="sm"
              variant="outline-primary"
              :disabled="currentRegions.length <= 0"
              @click="toogleRegionsDetails"
            >
              {{ $t('notifications.config_button') }}
            </b-button>
          </div>
        </b-col>
        <b-col cols="6" lg="2">
          <b-form-checkbox
            v-model="isRegionsPointGlobalEmailNotificationActive"
            size="sm"
            @change="toggleGlobalNotification('currentRegions', 'notifyByEmailAboutNewThreads', Number(isRegionsPointGlobalEmailNotificationActive))"
          >
            {{ $t('notifications.checkbox_email') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <div v-if="editRegionsNotification">
      <div
        v-for="region in currentRegions"
        :key="region.id"
        class="pb-2 pt-2"
      >
        <b-row align-v="center">
          <b-col cols="12" lg="6">
            {{ region.name }}
          </b-col>
          <b-col cols="12" lg="6">
            <b-form-radio-group
              v-model="region.email"
              :options="emailNotificationOptions"
              :name="'radio-button-' + region.id"
            />
          </b-col>
        </b-row>
        <hr class="my-2"> <!-- Linie -->
      </div>
    </div>

    <div class="pt-2">
      <h4>{{ $t('notifications.groups.title') }}</h4>
      <b-row>
        <b-col
          cols="6"
          lg="5"
        >
          <div>
            <b-button
              class="mt-2"
              size="sm"
              variant="outline-primary"
              :disabled="currentGroups.length <= 0"
              @click="toogleGroupsDetails"
            >
              {{ $t('notifications.config_button') }}
            </b-button>
          </div>
        </b-col>
        <b-col cols="6" lg="2">
          <b-form-checkbox
            v-model="isGroupsGlobalEmailNotificationActive"
            size="sm"
            @change="toggleGlobalNotification('currentGroups', 'notifyByEmailAboutNewThreads', Number(isGroupsGlobalEmailNotificationActive))"
          >
            {{ $t('notifications.checkbox_email') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <div v-if="editGroupsNotification">
      <div
        v-for="group in currentGroups"
        :key="group.id"
        class="pb-2 pt-2"
      >
        <b-row align-v="center">
          <b-col cols="12" lg="6">
            {{ group.name }}
          </b-col>
          <b-col cols="12" lg="6">
            <b-form-radio-group
              v-model="group.email"
              :options="emailNotificationOptions"
              :name="'radio-button-' + group.id"
            />
          </b-col>
        </b-row>
        <hr class="my-2"> <!-- Linie -->
      </div>
    </div>

    <div class="pt-2 pb-2">
      <h4>{{ $t('notifications.newsletter.title') }}</h4>
      <b-row>
        <b-col
          cols="8"
          lg="5"
        >
          {{ $t('notifications.newsletter.description') }}
        </b-col>
        <b-col cols="4" lg="6">
          <b-form-checkbox
            v-model="newsletterState "
            name="newsletter"
            size="sm"
          >
            {{ $t('notifications.checkbox_email') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <div v-if="userStore.isStoreManager" class="pt-2 pb-2">
      <h4>{{ $t('notifications.pickupReminder.title') }}</h4>
      <b-row>
        <b-col
          cols="8"
          lg="5"
        >
          {{ $t('notifications.pickupReminder.description') }}
        </b-col>
        <b-col cols="4" lg="6">
          <b-form-checkbox
            v-model="pickupReminderState"
            name="pickupReminder"
            size="sm"
          >
            {{ $t('notifications.checkbox_email') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <div class="pt-2 pb-2">
      <h4>{{ $t('notifications.mention.title') }}</h4>
      <b-row>
        <b-col cols="8" lg="5">
          {{ $t('notifications.mention.description') }}
        </b-col>
        <b-col cols="4" lg="6">
          <b-form-checkbox v-model="mentionState" size="sm">
            {{ $t('notifications.checkbox_bell') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <b-button
      size="sm"
      variant="primary"
      @click="updateNotificationSettings"
    >
      {{ $t('globals.save') }}
    </b-button>
  </div>
</template>

<script>
import {
  getFoodSharePointsNotification,
  listRegionsWithoutWorkingGroups,
  getThreadsNotification,
  listWorkingGroups,
  updateRegionsAndWorkgroupsNotification,
  setFoodSharePointsNotification,
  setThreadsNotification,
  getGeneralNotificationSettings,
  setGeneralNotificationSettings,
} from '@/api/notifications'
import Markdown from '@/components/Markdown/Markdown.vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import { pulseError, pulseSuccess } from '@/script'
import PushNotificationMixin from '@/mixins/PushNotificationMixin.js'
import { subscribeForPushNotifications, unsubscribeFromPushNotifications } from '@/pushNotifications'
import { useUserStore } from '@/stores/user'
import { useEnvironmentCheck } from '@/composables/useEnvironmentCheck'

const userStore = useUserStore()

export default {
  components: { Markdown, LoadingOverlay },
  mixins: [PushNotificationMixin],
  setup () {
    const { isSafari } = useEnvironmentCheck()
    return {
      isSafari,
      userStore,
      safariShareIcon: '![Safari Share Icon](/img/icon/safari-share-icon.svg)',
    }
  },
  data () {
    return {
      emailNotificationOptions: [
        { value: false, text: this.$t('notifications.checkbox_disabled') },
        { value: true, text: this.$t('notifications.checkbox_email') },
      ],
      subscription: {},
      infoMailState: null,
      newsletterState: false,
      pickupReminderState: true,
      mentionState: true,
      currentFoodSharePoints: [],
      currentThreads: [],
      currentRegions: [],
      currentGroups: [],
      editFoodSharePointNotification: false,
      editThreadsNotification: false,
      editRegionsNotification: false,
      editGroupsNotification: false,
      isFoodSharePointGlobalNotification: null,
      isFoodSharePointGlobalEmailNotificationActive: false,
      isRegionsPointGlobalEmailNotificationActive: false,
      isGroupsGlobalEmailNotificationActive: false,
      isThreadsGlobalEmailNotificationActive: false,
      isFoodSharePointGlobalBellNotificationActive: false,
    }
  },
  async mounted () {
    await userStore.fetchDetails()
    const generalNotificationSettings = await getGeneralNotificationSettings()
    this.newsletterState = generalNotificationSettings.emailOnNewsletter
    this.infoMailState = generalNotificationSettings.emailOnChatMessage
    this.pickupReminderState = generalNotificationSettings.emailOnStoreManagerPickupReminder
    this.mentionState = generalNotificationSettings.bellOnMention
    this.currentFoodSharePoints = await getFoodSharePointsNotification()
    this.currentThreads = await getThreadsNotification()
    this.currentRegions = await listRegionsWithoutWorkingGroups()
    this.currentGroups = await listWorkingGroups()
    this.isFoodSharePointGlobalEmailNotificationActive = !this.currentFoodSharePoints.some(foodSharePoint => !foodSharePoint.email)
    this.isFoodSharePointGlobalBellNotificationActive = !this.currentFoodSharePoints.some(foodSharePoint => !foodSharePoint.bell)
    this.isRegionsPointGlobalEmailNotificationActive = !this.currentRegions.some(region => !region.email)
    this.isGroupsGlobalEmailNotificationActive = !this.currentGroups.some(group => !group.email)
    this.isThreadsGlobalEmailNotificationActive = !this.currentThreads.some(threads => !threads.email)

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      this.pushNotificationState = false
    } else {
      await this.isSubscriptionValid()
    }
  },
  methods: {
    toggleFoodSharePointBell (value) {
      return value ? 2 : 0
    },
    toggleGlobalNotification (array, property, value) {
      this[array].forEach(item => {
        item[property] = value
      })
    },
    toogleFoodSharePointDetails () {
      this.editFoodSharePointNotification = !this.editFoodSharePointNotification
    },
    toogleThreadsDetails () {
      this.editThreadsNotification = !this.editThreadsNotification
    },
    toogleRegionsDetails () {
      this.editRegionsNotification = !this.editRegionsNotification
    },
    toogleGroupsDetails () {
      this.editGroupsNotification = !this.editGroupsNotification
    },
    async isSubscriptionValid () {
      try {
        const subscription = await (await navigator.serviceWorker.ready).pushManager.getSubscription()
        if (subscription) {
          this.pushNotificationState = this.isURL(subscription.endpoint) ? true : null
        } else {
          this.pushNotificationState = null
        }
      } catch {
        this.pushNotificationState = false
      }
    },
    isURL (variable) {
      const urlPattern = '^(http(s):\\/\\/.)[-a-zA-Z0-9@:%._\\+~#=]{2,256}\\.[a-z]{2,6}\\b([-a-zA-Z0-9@:%_\\+.~#?&//=]*)$'
      const regex = new RegExp(urlPattern)
      return regex.test(variable)
    },
    async trySetPushNotification () {
      try {
        if (!this.pushNotificationState) {
          await subscribeForPushNotifications()
          pulseSuccess(this.$t('settings.push.success'))
        } else {
          await unsubscribeFromPushNotifications()
          pulseSuccess(this.$t('settings.push.disabled'))
        }
        await this.isSubscriptionValid()
      } catch (error) {
        pulseError(this.$t('error_ajax'))
        throw error
      }
    },
    async updateNotificationSettings () {
      try {
        await setGeneralNotificationSettings({
          emailOnChatMessage: this.infoMailState,
          emailOnNewsletter: this.newsletterState,
          emailOnStoreManagerPickupReminder: this.pickupReminderState,
          bellOnMention: this.mentionState,
        })
        await setFoodSharePointsNotification(this.currentFoodSharePoints)
        await updateRegionsAndWorkgroupsNotification([...this.currentRegions, ...this.currentGroups].map(region => ({
          id: region.id, email: region.email,
        })))
        await setThreadsNotification(this.currentThreads.map(thread => ({ id: thread.id, email: thread.email })))
        pulseSuccess(this.$t('notifications.save_success'))
      } catch {
        pulseError(this.$t('error_ajax'))
      }
    },
    convertBooleanToNumber (value) {
      return value ? 1 : 0
    },
    convertNumberToBoolean (value) {
      return Boolean(Number(value))
    },
  },
}

</script>

<style lang="scss">
img[alt="Safari Share Icon"] {
  height: 24px;
  width: 24px;
  border: none;

  .dark-mode & {
    filter: invert(1);
  }
}
</style>

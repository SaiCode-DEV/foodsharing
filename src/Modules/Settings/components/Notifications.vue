<template>
  <div>
    <div class="pt-2">
      <div>
        <h4>{{ $i18n('notifications.chat.title') }}</h4>
        <b-row>
          <b-col
            cols="12"
            lg="5"
          >
            {{ $i18n('notifications.chat.description') }}
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
              {{ $i18n('notifications.checkbox_email') }}
            </b-form-checkbox>
          </b-col>
          <b-col
            cols="4"
            lg="3"
            class="pt-1"
          >
            <b-form-checkbox
              v-if="mayUsePushNotifications"
              :checked="usePushNotifications"
              :disabled="pushNotificationsLoading"
              size="sm"
              @change="updatePushNotifications"
            >
              {{ $i18n('notifications.checkbox_push') }}
            </b-form-checkbox>
          </b-col>
        </b-row>
      </div>
    </div>

    <div class="pt-2">
      <h4>{{ $i18n('notifications.foodSharePoints.title') }}</h4>
      <b-row>
        <b-col
          cols="12"
          lg="5"
        >
          {{ $i18n('notifications.foodSharePoints.description') }}
          <div>
            <b-button
              class="mt-2"
              size="sm"
              variant="outline-primary"
              :disabled="currentFoodSharePoints.length <= 0"
              @click="toogleFoodSharePointDetails"
            >
              {{ $i18n('notifications.config_button') }}
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
            {{ $i18n('notifications.checkbox_email') }}
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
            {{ $i18n('notifications.checkbox_bell') }}
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
            <b-form-radio-group
              v-model="foodSharePoint.infotype"
              :options="foodSharePointNotificationOptions"
              :name="'radio-button-' + foodSharePoint.id"
            />
          </b-col>
        </b-row>
        <hr class="my-2"> <!-- Linie -->
      </div>
    </div>

    <div class="pt-2">
      <h4>{{ $i18n('notifications.threads.title') }}</h4>
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
              {{ $i18n('notifications.config_button') }}
            </b-button>
          </div>
        </b-col>
        <b-col cols="6" lg="2">
          <b-form-checkbox
            v-model="isThreadsPointGlobalEmailNotificationActive"
            size="sm"
            @change="toggleGlobalNotification('currentThreads', 'infotype', Number(isThreadsPointGlobalEmailNotificationActive))"
          >
            {{ $i18n('notifications.checkbox_email') }}
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
            {{ thread.region_or_group_name }} / {{ thread.theme_name }}
          </b-col>
          <b-col cols="12" lg="6">
            <b-form-radio-group
              v-model="thread.infotype"
              :options="emailNotificationOptions"
              :name="'radio-button-' + thread.id"
            />
          </b-col>
        </b-row>
        <hr class="my-2"> <!-- Linie -->
      </div>
    </div>

    <div class="pt-2">
      <h4>{{ $i18n('notifications.regions.title') }}</h4>
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
              {{ $i18n('notifications.config_button') }}
            </b-button>
          </div>
        </b-col>
        <b-col cols="6" lg="2">
          <b-form-checkbox
            v-model="isRegionsPointGlobalEmailNotificationActive"
            size="sm"
            @change="toggleGlobalNotification('currentRegions', 'notifyByEmailAboutNewThreads', Number(isRegionsPointGlobalEmailNotificationActive))"
          >
            {{ $i18n('notifications.checkbox_email') }}
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
              v-model="region.notifyByEmailAboutNewThreads"
              :options="emailNotificationOptions"
              :name="'radio-button-' + region.id"
            />
          </b-col>
        </b-row>
        <hr class="my-2"> <!-- Linie -->
      </div>
    </div>

    <div class="pt-2">
      <h4>{{ $i18n('notifications.groups.title') }}</h4>
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
              {{ $i18n('notifications.config_button') }}
            </b-button>
          </div>
        </b-col>
        <b-col cols="6" lg="2">
          <b-form-checkbox
            v-model="isGroupsGlobalEmailNotificationActive"
            size="sm"
            @change="toggleGlobalNotification('currentGroups', 'notifyByEmailAboutNewThreads', Number(isGroupsGlobalEmailNotificationActive))"
          >
            {{ $i18n('notifications.checkbox_email') }}
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
              v-model="group.notifyByEmailAboutNewThreads"
              :options="emailNotificationOptions"
              :name="'radio-button-' + group.id"
            />
          </b-col>
        </b-row>
        <hr class="my-2"> <!-- Linie -->
      </div>
    </div>

    <div class="pt-2 pb-2">
      <h4>{{ $i18n('notifications.newsletter.title') }}</h4>
      <b-row>
        <b-col
          cols="8"
          lg="5"
        >
          {{ $i18n('notifications.newsletter.description') }}
        </b-col>
        <b-col cols="4" lg="6">
          <b-form-checkbox
            v-model="newsletterState "
            name="newsletter"
            size="sm"
          >
            {{ $i18n('notifications.checkbox_email') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <div v-if="userStore.isStoreManager" class="pt-2 pb-2">
      <h4>{{ $i18n('notifications.pickupReminder.title') }}</h4>
      <b-row>
        <b-col
          cols="8"
          lg="5"
        >
          {{ $i18n('notifications.pickupReminder.description') }}
        </b-col>
        <b-col cols="4" lg="6">
          <b-form-checkbox
            v-model="pickupReminderState"
            name="pickupReminder"
            size="sm"
          >
            {{ $i18n('notifications.checkbox_email') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <div class="pt-2 pb-2">
      <h4>{{ $i18n('notifications.mention.title') }}</h4>
      <b-row>
        <b-col cols="8" lg="5">
          {{ $i18n('notifications.mention.description') }}
        </b-col>
        <b-col cols="4" lg="6">
          <b-form-checkbox v-model="mentionState" size="sm">
            {{ $i18n('notifications.checkbox_bell') }}
          </b-form-checkbox>
        </b-col>
      </b-row>
    </div>

    <b-button
      size="sm"
      variant="primary"
      @click="updateNotificationSettings"
    >
      {{ $i18n('globals.save') }}
    </b-button>
  </div>
</template>

<script>
import {
  getFoodSharePointsNotification,
  listRegionsWithoutWorkingGroups,
  getThreadsNotification,
  getUserNotification,
  listWorkingGroups,
  updateRegionsAndWorkgroupsNotification,
  setFoodSharePointsNotification,
  setThreadsNotification,
  setUserNotification,
  getPickupReminderNotification,
  setPickupReminderNotification,
  setMentionNotification,
  getMentionNotification,
} from '@/api/notifications'
import { pulseError, pulseSuccess } from '@/script'
import PushNotificationMixin from '@/mixins/PushNotificationMixin.js'
import { subscribeForPushNotifications, unsubscribeFromPushNotifications } from '@/pushNotifications'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

export default {
  mixins: [PushNotificationMixin],
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      foodSharePointNotificationOptions: [
        { value: 0, text: this.$i18n('notifications.checkbox_disabled') },
        { value: 1, text: this.$i18n('notifications.checkbox_email') },
        { value: 2, text: this.$i18n('notifications.checkbox_bell') },
      ],
      emailNotificationOptions: [
        { value: 0, text: this.$i18n('notifications.checkbox_disabled') },
        { value: 1, text: this.$i18n('notifications.checkbox_email') },
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
      isThreadsPointGlobalEmailNotificationActive: false,
      isFoodSharePointGlobalBellNotificationActive: false,
    }
  },
  async mounted () {
    await userStore.fetchDetails()
    this.subscription = await getUserNotification()
    this.newsletterState = this.convertNumberToBoolean(this.subscription.newsletter)
    this.infoMailState = this.convertNumberToBoolean(this.subscription.infomail_message)
    this.currentFoodSharePoints = await getFoodSharePointsNotification()
    this.currentThreads = await getThreadsNotification()
    this.currentRegions = await listRegionsWithoutWorkingGroups()
    this.currentGroups = await listWorkingGroups()
    if (userStore.isStoreManager) {
      this.pickupReminderState = this.convertNumberToBoolean(await getPickupReminderNotification())
    }
    this.mentionState = this.convertNumberToBoolean(await getMentionNotification())
    this.isFoodSharePointGlobalEmailNotificationActive = this.currentFoodSharePoints.some(foodSharePoint => foodSharePoint.infotype === 1)
    this.isRegionsPointGlobalEmailNotificationActive = this.currentRegions.some(region => region.notifyByEmailAboutNewThreads === 1)
    this.isGroupsGlobalEmailNotificationActive = this.currentGroups.some(group => group.notifyByEmailAboutNewThreads === 1)
    this.isThreadsPointGlobalEmailNotificationActive = this.currentThreads.some(threads => threads.infotype === 1)
    this.isFoodSharePointGlobalBellNotificationActive = this.currentFoodSharePoints.some(foodSharePoint => foodSharePoint.infotype === 2)

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
          pulseSuccess(this.$i18n('settings.push.success'))
        } else {
          await unsubscribeFromPushNotifications()
          pulseSuccess(this.$i18n('settings.push.disabled'))
        }
        await this.isSubscriptionValid()
      } catch (error) {
        pulseError(this.$i18n('error_ajax'))
        throw error
      }
    },
    async updateNotificationSettings () {
      try {
        const newsletter = this.convertBooleanToNumber(this.newsletterState)
        const infoMailState = this.convertBooleanToNumber(this.infoMailState)
        await setUserNotification(newsletter, infoMailState)
        await setFoodSharePointsNotification(this.currentFoodSharePoints)
        await updateRegionsAndWorkgroupsNotification(this.currentRegions.map(region => {
          return { id: region.id, notifyByEmailAboutNewThreads: region.notifyByEmailAboutNewThreads === 1 }
        }))
        await updateRegionsAndWorkgroupsNotification(this.currentGroups.map(group => {
          return { id: group.id, notifyByEmailAboutNewThreads: group.notifyByEmailAboutNewThreads === 1 }
        }))
        await setThreadsNotification(this.currentThreads)
        if (userStore.isStoreManager) {
          await setPickupReminderNotification(this.pickupReminderState)
        }
        await setMentionNotification(this.mentionState)
        pulseSuccess(this.$i18n('notifications.success'))
      } catch {
        pulseError(this.$i18n('error_ajax'))
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

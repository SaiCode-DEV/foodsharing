<template>
  <div>
    <div class="head ui-widget-header">
      {{ $i18n('settings.notifications') }}
    </div>
    <div class="ui-widget-content corner-bottom margin-bottom ui-padding">
      <div>
        <h4>{{ $i18n('notifications.chat.title') }}</h4>
        <b-row>
          <b-col
            cols="12"
            lg="5"
          >
            {{ $i18n('notifications.chat.description') }}
          </b-col>
          <b-col lg="1" />
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
              v-if="pushNotificationState === null || pushNotificationState === true"
              :checked="pushNotificationState"
              size="sm"
              @change="trySetPushNotification"
            >
              {{ $i18n('notifications.checkbox_push') }}
            </b-form-checkbox>
          </b-col>
        </b-row>
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
            lg="1"
            class="pt-1"
          >
            <b-form-checkbox
              v-model="isFoodSharePointGlobalNotificationActive"
              switch
              size="sm"
              @change="toggleFoodSharePointGlobalNotification"
            />
          </b-col>
          <b-col
            cols="4"
            lg="2"
            class="pt-1"
          >
            <b-form-checkbox
              v-model="isFoodSharePointGlobalEmailNotificationActive"
              size="sm"
              @change="toggleFoodSharePointGlobalEmailNotification"
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
              @change="toggleFoodSharePointGlobalBellNotification"
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
              @change="toggleThreadsGlobalEmailNotification"
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
              @change="toggleRegionsGlobalEmailNotification"
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
              @change="toggleGroupsGlobalEmailNotification"
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

      <b-button
        size="sm"
        variant="primary"
        @click="updateNotificationSettings"
      >
        {{ $i18n('globals.save') }}
      </b-button>
    </div>
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
} from '@/api/notifications'
import { pulseError, pulseSuccess } from '@/script'
import i18n from '@/helper/i18n'
import { subscribeForPushNotifications, unsubscribeFromPushNotifications } from '@/pushNotifications'

export default {
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
      pushNotificationState: null,
      infoMailState: null,
      newsletterState: false,
      currentFoodSharePoints: [],
      currentThreads: [],
      currentRegions: [],
      currentGroups: [],
      editFoodSharePointNotification: false,
      editThreadsNotification: false,
      editRegionsNotification: false,
      editGroupsNotification: false,
      isFoodSharePointGlobalNotification: null,
    }
  },
  computed: {
    isFoodSharePointGlobalNotificationActive: {
      get () {
        return this.currentFoodSharePoints.some(foodSharePoint => foodSharePoint.infotype !== 0)
      },
      set (value) {
        // No action needed since this is a read-only computed property
      },
    },
    isFoodSharePointGlobalEmailNotificationActive: {
      get () {
        return this.currentFoodSharePoints.some(foodSharePoint => foodSharePoint.infotype === 1)
      },
      set (value) {
        // No action needed since this is a read-only computed property
      },
    },
    isGroupsGlobalEmailNotificationActive: {
      get () {
        return this.currentGroups.some(group => group.notifyByEmailAboutNewThreads === 1)
      },
      set (value) {
        // No action needed since this is a read-only computed property
      },
    },
    isThreadsPointGlobalEmailNotificationActive: {
      get () {
        return this.currentThreads.some(threads => threads.infotype === 1)
      },
      set (value) {
        // No action needed since this is a read-only computed property
      },
    },
    isRegionsPointGlobalEmailNotificationActive: {
      get () {
        return this.currentRegions.some(region => region.notifyByEmailAboutNewThreads === 1)
      },
      set (value) {
        // No action needed since this is a read-only computed property
      },
    },
    isFoodSharePointGlobalBellNotificationActive: {
      get () {
        return this.currentFoodSharePoints.some(foodSharePoint => foodSharePoint.infotype === 2)
      },
      set (value) {
        // No action needed since this is a read-only computed property
      },
    },
  },
  async mounted () {
    this.subscription = await getUserNotification()
    this.newsletterState = this.convertNumberToBoolean(this.subscription.newsletter)
    this.infoMailState = this.convertNumberToBoolean(this.subscription.infomail_message)
    this.currentFoodSharePoints = await getFoodSharePointsNotification()
    this.currentThreads = await getThreadsNotification()
    this.currentRegions = await listRegionsWithoutWorkingGroups()
    this.currentGroups = await listWorkingGroups()

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      this.pushNotificationState = false
    } else {
      await this.isSubscriptionValid()
    }
  },
  methods: {
    toggleFoodSharePointGlobalEmailNotification () {
      const value = !this.isFoodSharePointGlobalEmailNotificationActive ? 1 : 0
      this.currentFoodSharePoints.forEach(foodSharePoint => {
        foodSharePoint.infotype = value
      })
    },
    toggleGroupsGlobalEmailNotification () {
      const value = !this.isGroupsGlobalEmailNotificationActive ? 1 : 0
      this.currentGroups.forEach(group => {
        group.notifyByEmailAboutNewThreads = value
      })
    },
    toggleRegionsGlobalEmailNotification () {
      const value = !this.isRegionsPointGlobalEmailNotificationActive ? 1 : 0
      this.currentRegions.forEach(region => {
        region.notifyByEmailAboutNewThreads = value
      })
    },
    toggleThreadsGlobalEmailNotification () {
      const value = !this.isThreadsPointGlobalEmailNotificationActive ? 1 : 0
      this.currentThreads.forEach(thread => {
        thread.infotype = value
      })
    },
    toggleFoodSharePointGlobalBellNotification () {
      const value = !this.isFoodSharePointGlobalBellNotificationActive ? 2 : 0
      this.currentFoodSharePoints.forEach(foodSharePoint => {
        foodSharePoint.infotype = value
      })
    },
    toggleFoodSharePointGlobalNotification () {
      if (!this.isFoodSharePointGlobalNotification) {
        this.currentFoodSharePoints.forEach(foodSharePoint => {
          foodSharePoint.infotype = 0
        })
      }
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
          pulseSuccess(i18n('settings.push.success'))
        } else {
          await unsubscribeFromPushNotifications()
          pulseSuccess(i18n('settings.push.disabled'))
        }
        await this.isSubscriptionValid()
      } catch (error) {
        pulseError(i18n('error_ajax'))
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
        pulseSuccess(i18n('notifications.success'))
      } catch {
        pulseError(i18n('error_ajax'))
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

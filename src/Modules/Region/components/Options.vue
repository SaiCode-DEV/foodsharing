<template>
  <div>
    <Container
      :title="$t('regionOptions.userRelated.heading', { bezirk: regionName })"
      :tooltip-key="$t('regionOptions.userRelated.info')"
    >
      <div class="list-group-item">
        <b-form-checkbox
          v-model="isAddressChangeNotificationEnabled"
          :disabled="!mayEditUserRelated"
        >
          {{ $t('regionOptions.enableAddressChangeNotification') }}
        </b-form-checkbox>
      </div>
      <ContainerButton
        v-if="mayEditUserRelated"
        text-key="regionOptions.save"
        variant="secondary"
        @click="saveUserRelatedOptions"
      />
    </Container>

    <Container
      :title="$t('regionOptions.pickuprule.heading', { bezirk: regionName })"
      :tooltip-key="$t('regionOptions.pickuprule.info')"
    >
      <div class="list-group-item">
        <b-form-checkbox
          id="activeRegionPickupRule"
          v-model="isRegionPickupRuleActive"
          class="mt-1"
          :disabled="!mayEditPickupRule"
        >
          {{ $t('regionOptions.regionPickupRuleActive') }}
        </b-form-checkbox>
      </div>
      <div class="list-group-item">
        <b-row class="my-1">
          <b-col>
            <label :class="{disabled: disablePickupRuleSettings}">
              {{ $t('regionOptions.regionPickupTimespan') }}: {{ regionPickupRuleTimespanDays }}
            </label>
            <b-form-input
              v-model="regionPickupRuleTimespanDays"
              type="range"
              min="1"
              max="31"
              :disabled="disablePickupRuleSettings"
            />
          </b-col>
        </b-row>
        <b-row class="my-1">
          <b-col>
            <label :class="{disabled: disablePickupRuleSettings}">
              {{ $t('regionOptions.regionPickupLimitNumber') }}: {{ regionPickupRuleLimitNumber }}
            </label>
            <b-form-input
              v-model="regionPickupRuleLimitNumber"
              type="range"
              min="1"
              max="14"
              :disabled="disablePickupRuleSettings"
              @change="onChangeMax()"
            />
          </b-col>
        </b-row>
        <b-row class="my-1">
          <b-col>
            <label :class="{disabled: disablePickupRuleSettings}">
              {{ $t('regionOptions.regionPickupLimitDayNumber') }}: {{ regionPickupRuleLimitDayNumber }}
            </label>
            <b-form-input
              v-model="regionPickupRuleLimitDayNumber"
              type="range"
              min="1"
              :max="rangeDayLimit"
              :disabled="disablePickupRuleSettings"
            />
          </b-col>
        </b-row>
        <b-row class="mt-1">
          <b-col>
            <label :class="{disabled: disablePickupRuleSettings}">
              {{ $t('regionOptions.regionPickupInactiveHours') }}:
            </label>
          </b-col>
          <b-col>
            <b-form-select
              v-model="regionPickupRuleInactiveHours"
              :options="optionsIgnoreRuleHours"
              :disabled="disablePickupRuleSettings"
            />
          </b-col>
        </b-row>
      </div>
      <div class="list-group-item">
        <b-table
          v-if="regionPickupRuleActiveStoreList && regionPickupRuleActiveStoreList.length > 0"
          :fields="fields"
          :items="regionPickupRuleActiveStoreList"
          :sort-by="sortBy"
          striped
          hover
          small
          caption-top
        >
          <template #cell(storeName)="row">
            <router-link
              :to="$url('store', row.item.id)"
              class="ui-corner-all"
            >
              {{ row.item.name }}
            </router-link>
          </template>
        </b-table>
        <span v-else v-text="$t('regionOptions.noPickupRuleActiveStores')" />
      </div>
      <ContainerButton
        v-if="mayEditPickupRule"
        text-key="regionOptions.save"
        variant="secondary"
        @click="savePickupRuleOptions"
      />
    </Container>

    <Container
      :title="$t('regionOptions.reporting.heading', { bezirk: regionName })"
      :tooltip-key="$t('regionOptions.reporting.info')"
    >
      <div class="list-group-item">
        <b-form-checkbox
          v-model="isReportButtonEnabled"
          :disabled="!mayEditReporting"
        >
          {{ $t('regionOptions.enableReportButton') }}
          <Info info-key="reportReasons" />
        </b-form-checkbox>
        <div class="ml-4">
          <b-form-group>
            <b-form-radio-group
              v-model="selectedReportReasonOptions"
              :options="reportReasonOptionsRadio"
              name="radio-options-slots"
              stacked
              :disabled="disableReportReasonSettings"
            />
          </b-form-group>
          <b-form-checkbox
            v-model="isReportReasonOtherEnabled"
            :disabled="disableReportReasonSettings"
          >
            {{ $t('regionOptions.regionReportReasonOther') }}
          </b-form-checkbox>
        </div>
      </div>
      <div class="list-group-item">
        <b-form-checkbox
          v-model="isMediationButtonEnabled"
          :disabled="!mayEditReporting"
        >
          {{ $t('regionOptions.enableMediationButton') }}
        </b-form-checkbox>
      </div>
      <ContainerButton
        v-if="mayEditReporting"
        text-key="regionOptions.save"
        variant="secondary"
        @click="saveReportingOptions"
      />
    </Container>
  </div>
</template>

<script>
import { getRegionOptionPermissions, getRegionOptions, setRegionOptions } from '@/api/regions'
import { hideLoader, pulseError, pulseInfo, showLoader } from '@/script'
import Container from '@/components/Container/Container.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import Info from '@/components/Help/Info.vue'

export default {
  components: { Container, ContainerButton, Info },
  props: {
    regionId: { type: Number, required: true },
    regionName: { type: String, default: '' },
  },
  data () {
    return {
      mayEditReporting: false,
      mayEditPickupRule: false,
      mayEditUserRelated: false,
      regionPickupRuleActiveStoreList: null,
      isReportButtonEnabled: false,
      isMediationButtonEnabled: false,
      isAddressChangeNotificationEnabled: false,
      isRegionPickupRuleActive: false,
      regionPickupRuleTimespanDays: 0,
      regionPickupRuleLimitNumber: 0,
      regionPickupRuleLimitDayNumber: 0,
      regionPickupRuleInactiveHours: 0,
      rangeDayLimit: 100,
      selectedReportReasonOptions: 1,
      isReportReasonOtherEnabled: false,
      sortBy: 'storeName',
      fields: [{
        key: 'storeName',
        label: this.$t('regionOptions.regionPickupRuleActiveStoreList'),
        sortable: true,
      },
      ],
      optionsIgnoreRuleHours: [
        { text: '4', value: 4 },
        { text: '8', value: 8 },
        { text: '12', value: 12 },
        { text: '16', value: 16 },
        { text: '24', value: 24 },
        { text: '36', value: 36 },
        { text: '48', value: 48 },
        { text: '60', value: 60 },
        { text: '72', value: 72 },
      ],
      reportReasonOptionsRadio: [
        { text: this.$t('regionOptions.regionReportReasonSimple'), value: 1 },
        { text: this.$t('regionOptions.regionReportReasonCategoryB'), value: 2 },
      ],
    }
  },
  computed: {
    mayEdit () {
      return this.mayEditReporting || this.mayEditPickupRule || this.mayEditUserRelated
    },
    disablePickupRuleSettings () {
      return !this.mayEditPickupRule || !this.isRegionPickupRuleActive
    },
    disableReportReasonSettings () {
      return !this.mayEditReporting || !this.isReportButtonEnabled
    },
  },
  async mounted () {
    showLoader()
    try {
      const response = getRegionOptions(this.regionId).then(response => {
        Object.assign(this, response)
      })
      const permissions = getRegionOptionPermissions(this.regionId).then(permissions => {
        this.mayEditReporting = permissions.maySetRegionOptionsReportButtons
        this.mayEditPickupRule = permissions.maySetRegionOptionsRegionPickupRule
        this.mayEditUserRelated = permissions.maySetRegionOptionsUserRelated

        this.regionPickupRuleActiveStoreList = permissions.regionPickupRuleActiveStoreList
      })
      await Promise.all([response, permissions])
    } catch (err) {
      pulseError(this.$t('error_unexpected'))
    }
    hideLoader()
  },
  methods: {
    onChangeMax () {
      if (this.regionPickupRuleLimitDayNumber > this.regionPickupRuleLimitNumber) {
        this.regionPickupRuleLimitDayNumber = this.regionPickupRuleLimitNumber
      }
      this.rangeDayLimit = this.regionPickupRuleLimitNumber
    },
    async saveUserRelatedOptions () {
      this.trySendOptions({
        isAddressChangeNotificationEnabled: this.isAddressChangeNotificationEnabled,
      })
    },
    async savePickupRuleOptions () {
      this.trySendOptions({
        isRegionPickupRuleActive: this.isRegionPickupRuleActive,
        regionPickupRuleTimespanDays: this.regionPickupRuleTimespanDays,
        regionPickupRuleLimitNumber: this.regionPickupRuleLimitNumber,
        regionPickupRuleLimitDayNumber: this.regionPickupRuleLimitDayNumber,
        regionPickupRuleInactiveHours: this.regionPickupRuleInactiveHours,
      })
    },
    async saveReportingOptions () {
      this.trySendOptions({
        isReportButtonEnabled: this.isReportButtonEnabled,
        isMediationButtonEnabled: this.isMediationButtonEnabled,
        selectedReportReasonOptions: this.selectedReportReasonOptions,
        isReportReasonOtherEnabled: this.isReportReasonOtherEnabled,
      })
    },
    async trySendOptions (optionsToSave) {
      showLoader()
      try {
        await setRegionOptions(
          this.regionId,
          optionsToSave,
        )
        pulseInfo(this.$t('regionOptions.success'))
      } catch (err) {
        console.error(err)
        pulseError(this.$t('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

<style lang="scss" scoped>
label.disabled {
  color: #6c757d;
}
label:last-child {
  margin-bottom: 0;
}
</style>

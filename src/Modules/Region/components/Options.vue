<template>
  <Container :title="$i18n('regionOptions.header_page', { bezirk: regionName })">
    <div class="list-group-item">
      <b-form-checkbox
        v-model="isReportButtonEnabled"
        :disabled="!maySetReport"
      >
        {{ $i18n('regionOptions.enableReportButton') }}
        <Info info-key="reportReasons" />
      </b-form-checkbox>
      <div class="mb-2 mx-4">
        <b-form-group>
          <b-form-radio-group
            v-model="selectedReportReasonOptions"
            :options="reportReasonOptionsRadio"
            name="radio-options-slots"
            stacked
            :disabled="!isReportButtonEnabled || !maySetReport"
          />
        </b-form-group>
        <b-form-checkbox
          v-model="isReportReasonOtherEnabled"
          :disabled="!isReportButtonEnabled || !maySetReport"
        >
          {{ $i18n('regionOptions.regionReportReasonOther') }}
        </b-form-checkbox>
      </div>
      <b-form-checkbox
        v-model="isMediationButtonEnabled"
        :disabled="!maySetReport"
      >
        {{ $i18n('regionOptions.enableMediationButton') }}
      </b-form-checkbox>
      <b-form-checkbox
        v-model="isAddressChangeNotificationEnabled"
        :disabled="!maySetReport"
      >
        {{ $i18n('regionOptions.enableAddressChangeNotification') }}
      </b-form-checkbox>
    </div>
    <div class="list-group-item">
      <b-form-checkbox
        id="activeRegionPickupRule"
        v-model="regionPickupRuleActive"
        class="mt-1"
        :disabled="!maySetRule"
      >
        {{ $i18n('regionOptions.regionPickupRuleActive') }}
      </b-form-checkbox>
      <b-row class="my-1">
        <b-col>
          <label>{{ $i18n('regionOptions.regionPickupTimespan') }}: {{ regionPickupRuleTimespanDays }}</label>
          <b-form-input
            v-model="regionPickupRuleTimespanDays"
            type="range"
            min="1"
            max="31"
            :disabled="!maySetRule || !regionPickupRuleActive"
          />
        </b-col>
      </b-row>
      <b-row class="my-1">
        <b-col>
          <label>{{ $i18n('regionOptions.regionPickupLimitNumber') }}: {{ regionPickupRuleLimitNumber }} </label>
          <b-form-input
            v-model="regionPickupRuleLimitNumber"
            type="range"
            min="1"
            max="14"
            :disabled="!maySetRule || !regionPickupRuleActive"
            @change="onChangeMax()"
          />
        </b-col>
      </b-row>
      <b-row class="my-1">
        <b-col>
          <label>{{ $i18n('regionOptions.regionPickupLimitDayNumber') }}: {{ regionPickupRuleLimitDayNumber }}</label>
          <b-form-input
            v-model="regionPickupRuleLimitDayNumber"
            type="range"
            min="1"
            :max="rangeDayLimit"
            :disabled="!maySetRule || !regionPickupRuleActive"
          />
        </b-col>
      </b-row>
      <b-row class="my-1">
        <b-col>
          <label>{{ $i18n('regionOptions.regionPickupInactiveHours') }}:</label>
        </b-col>
        <b-col>
          <b-form-select
            v-model="regionPickupRuleInactiveHours"
            :options="optionsIgnoreRuleHours"
            :disabled="!maySetRule || !regionPickupRuleActive"
          />
        </b-col>
      </b-row>
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
          <a
            :href="$url('store', row.item.storeId)"
            class="ui-corner-all"
          >
            {{ row.value }}
          </a>
        </template>
      </b-table>
      <span v-else v-text="$i18n('regionOptions.noPickupRuleActiveStores')" />
    </div>
    <ContainerButton
      v-if="maySetReport || maySetRule"
      text-key="regionOptions.save"
      variant="secondary"
      @click="trySendOptions"
    />
  </Container>
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
      maySetReport: false,
      maySetRule: false,
      regionPickupRuleActiveStoreList: null,
      isReportButtonEnabled: false,
      isMediationButtonEnabled: false,
      isAddressChangeNotificationEnabled: false,
      regionPickupRuleActive: false,
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
        label: this.$i18n('regionOptions.regionPickupRuleActiveStoreList'),
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
        { text: this.$i18n('regionOptions.regionReportReasonSimple'), value: 1 },
        { text: this.$i18n('regionOptions.regionReportReasonCategoryB'), value: 2 },
      ],
    }
  },
  async mounted () {
    showLoader()
    try {
      const response = getRegionOptions(this.regionId).then(response => {
        Object.assign(this, response)
      })
      const permissions = getRegionOptionPermissions(this.regionId).then(permissions => {
        this.maySetReport = permissions.maySetRegionOptionsReportButtons
        this.maySetRule = permissions.maySetRegionOptionsRegionPickupRule
        this.regionPickupRuleActiveStoreList = permissions.regionPickupRuleActiveStoreList
      })
      await Promise.all([response, permissions])
    } catch (err) {
      pulseError(this.$i18n('error_unexpected'))
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
    async trySendOptions () {
      showLoader()
      try {
        await setRegionOptions(
          this.regionId,
          this.isReportButtonEnabled,
          this.isMediationButtonEnabled,
          this.regionPickupRuleActive,
          this.regionPickupRuleTimespanDays,
          this.regionPickupRuleLimitNumber,
          this.regionPickupRuleLimitDayNumber,
          this.regionPickupRuleInactiveHours,
          this.selectedReportReasonOptions,
          this.isReportReasonOtherEnabled,
          this.isAddressChangeNotificationEnabled,
        )
        pulseInfo(this.$i18n('regionOptions.success'))
      } catch (err) {
        console.error(err)
        pulseError(this.$i18n('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

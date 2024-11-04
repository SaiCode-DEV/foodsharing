<template>
  <Container :title="$i18n('regionOptions.header_page', { bezirk: regionName })">
    <div class="list-group-item">
      <b-form-checkbox
        v-model="reportButtonEnabled"
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
            :disabled="!reportButtonEnabled || !maySetReport"
          />
        </b-form-group>
        <b-form-checkbox
          v-model="reportReasonOtherEnabled"
          :disabled="!reportButtonEnabled || !maySetReport"
        >
          {{ $i18n('regionOptions.regionReportReasonOther') }}
        </b-form-checkbox>
      </div>
      <b-form-checkbox
        v-model="mediationButtonEnabled"
        :disabled="!maySetReport"
      >
        {{ $i18n('regionOptions.enableMediationButton') }}
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
          <label>{{ $i18n('regionOptions.regionPickupTimespan') }}: {{ pickupRuleTimespan }}</label>
          <b-form-input
            v-model="pickupRuleTimespan"
            type="range"
            min="1"
            max="31"
            :disabled="!maySetRule || !regionPickupRuleActive"
          />
        </b-col>
      </b-row>
      <b-row class="my-1">
        <b-col>
          <label>{{ $i18n('regionOptions.regionPickupLimitNumber') }}: {{ pickupRuleLimit }} </label>
          <b-form-input
            v-model="pickupRuleLimit"
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
          <label>{{ $i18n('regionOptions.regionPickupLimitDayNumber') }}: {{ pickupRuleLimitDay }}</label>
          <b-form-input
            v-model="pickupRuleLimitDay"
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
            v-model="pickupRuleInactive"
            :options="optionsIgnoreRuleHours"
            :disabled="!maySetRule || !regionPickupRuleActive"
          />
        </b-col>
      </b-row>
      <b-table
        v-if="pageData.regionPickupRuleActiveStoreList.length"
        :fields="fields"
        :items="pageData.regionPickupRuleActiveStoreList"
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
import { setRegionOptions } from '@/api/regions'
import { hideLoader, pulseError, pulseInfo, showLoader } from '@/script'
import Container from '@/components/Container/Container.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import Info from '@/components/Help/Info.vue'

export default {
  components: { Container, ContainerButton, Info },
  props: {
    regionId: { type: Number, required: true },
    regionName: { type: String, default: '' },
    pageData: { type: Object, default: () => {} },
    regionPickupRuleActiveStoreList: {
      type: Array,
      default: () => [],
    },
  },
  data () {
    return {
      maySetReport: this.pageData.maySetRegionOptionsReportButtons,
      maySetRule: this.pageData.maySetRegionOptionsRegionPickupRule,
      reportButtonEnabled: this.pageData.isReportButtonEnabled,
      mediationButtonEnabled: this.pageData.isMediationButtonEnabled,
      regionPickupRuleActive: this.pageData.isRegionPickupRuleActive,
      pickupRuleTimespan: this.pageData.regionPickupRuleTimespanDays,
      pickupRuleLimit: this.pageData.regionPickupRuleLimitNumber,
      pickupRuleLimitDay: this.pageData.regionPickupRuleLimitDayNumber,
      pickupRuleInactive: this.pageData.regionPickupRuleInactiveHours,
      rangeDayLimit: this.pageData.rangeDayLimitNum,
      selectedReportReasonOptions: this.pageData.selectedReportReasonOptions,
      reportReasonOtherEnabled: !!this.pageData.reportReasonOtherEnabled,
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
  methods: {
    onChangeMax () {
      if (this.pickupRuleLimitDay > this.pickupRuleLimit) {
        this.pickupRuleLimitDay = this.pickupRuleLimit
      }
      this.rangeDayLimit = this.pickupRuleLimit
    },
    async trySendOptions () {
      showLoader()
      try {
        await setRegionOptions(this.regionId, this.reportButtonEnabled, this.mediationButtonEnabled, this.regionPickupRuleActive, this.pickupRuleTimespan, this.pickupRuleLimit, this.pickupRuleLimitDay, this.pickupRuleInactive, this.selectedReportReasonOptions, this.reportReasonOtherEnabled)
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

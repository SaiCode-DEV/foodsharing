<template>
  <Container
    :tag="`store-infos-${storeId}`"
    wrap-content="p-2"
  >
    <template #title>
      <h5>
        {{ $t('store.info_container') }}
        <span class="text-nowrap">(
          <i
            v-b-tooltip="storeCategoryTypeStatus"
            :class="['fas', storeCategoryTypeIcon]"
            style="cursor: help;"
          />
          <span class="ml-1">{{ $t('map.filters.stores.type.' + categoryType) }}</span>
          )
        </span>
      </h5>
    </template>
    <div
      v-show="displayInfos"
      class="store-desc"
    >
      <div
        id="inputAdress"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $t('store.address') }}
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <div>
            {{ street }} <br>
            {{ postcode }} {{ city }} <br>
            <a :href="$url('map', { storeId: storeId })">
              <i class="fas fa-map-marker-alt" />
              {{ $t('store.to_map') }}
            </a>
          </div>
          <NavigateWithSelector
            :latitude="latitude"
            :longitude="longitude"
            vertical
            small
          />
        </div>
      </div>
      <div
        id="inputParticularities"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $t('store.particularities') }}
        </div>
        <Markdown :source="particularitiesDescription" />
      </div>
      <div
        v-if="chainDetails"
        id="chainParticularities"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $t('store.particularities_chain') }}
          <Info info-key="store_chain_info" style="margin-top: -0.5rem; margin-bottom: -0.5rem;" />
        </div>
        <span>
          <span>{{ $t('store.part_of_chain') }}</span>
          <strong>{{ chainDetails.name }}</strong>
        </span>
        <div v-if="chainDetails.kams.length" class="d-flex align-items-center">
          <span class="flex-grow-1" v-text="$t('store.chain_has_kams')" />
          <AvatarStack :users="chainDetails.kams" />
        </div>
        <Markdown
          v-if="chainDetails.information"
          class="border-top pt-1 mt-2"
          :source="chainDetails.information"
        />
      </div>
      <div
        v-if="categoryType === STORE_CATEGORY_PICKUP || categoryType === STORE_CATEGORY_GIVING"
        id="inputAverageCollectionQuantity"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $t('store.average_collection_quantity') }}
        </div>
        <div>
          {{ collectionQuantity }}
        </div>
      </div>
      <div
        id="inputAttribution"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $t('store.attribution') }}
        </div>
        {{ pressInfo }}
      </div>
      <div
        v-if="useRegionPickupRules"
        id="inputUseRegionPickupRules"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $t('store.regionPickupRules') }}
        </div>
        <span>{{ $t('store.useRegionPickupRules') }}</span><br>
        <span>{{ $t('store.regionPickupRuleLong', {regionPickupRuleTimespan, regionPickupRuleLimit, regionPickupRuleLimitDay, regionPickupRuleInactive}) }}</span>
      </div>
      <div
        v-if="isDateValid(lastFetchDate)"
        id="inputMyLastPickup"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $t('store.my_last_slot') }}
        </div>
        <span>
          {{ $dateFormatter.date(lastFetchDate) }}
        </span>
        ({{ $dateFormatter.relativeTime(lastFetchDate) }})
      </div>
    </div>
  </Container>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown.vue'
import Container from '@/components/Container/Container.vue'
import storeEntryMixin from '@/mixins/storeEntryMixin'
import { STORE_PUBLICITY_AND_STICKER_OPTIONS } from '@/stores/stores'
import { useStoreStore } from '@/stores/store'
import { STORE_CATEGORY_PICKUP } from '@/constants/storeCategoryTypes'
import NavigateWithSelector from '@/components/UI/NavigateWithSelector.vue'
import AvatarStack from '../Avatar/AvatarStack.vue'
import Info from '../Help/Info.vue'

export default {
  components: { Markdown, Container, NavigateWithSelector, AvatarStack, Info },
  mixins: [storeEntryMixin],
  props: {
    particularitiesDescription: {
      type: String,
      default: '',
    },
    chainDetails: {
      type: Object,
      default: null,
    },
    storeTitle: {
      type: String,
      default: null,
    },
    latitude: {
      type: Number,
      default: 0,
    },
    longitude: {
      type: Number,
      default: 0,
    },
    street: {
      type: String,
      default: '',
    },
    postcode: {
      type: String,
      default: '',
    },
    city: {
      type: String,
      default: '',
    },
    lastFetchDate: {
      type: [Date, String],
      default: null,
    },
    press: {
      type: Number,
      default: 2,
    },
    regionPickupRules: {
      type: Boolean,
      default: false,
    },
    regionPickupRuleActive: {
      type: Boolean,
      default: false,
    },
    regionPickupRuleTimespan: {
      type: Number,
      default: 0,
    },
    regionPickupRuleLimit: {
      type: Number,
      default: 0,
    },
    regionPickupRuleLimitDay: {
      type: Number,
      default: 0,
    },
    regionPickupRuleInactive: {
      type: Number,
      default: 0,
    },
    weightType: {
      type: Number,
      default: null,
    },
    storeId: {
      type: Number,
      required: true,
    },
    categoryType: {
      type: Number,
      default: STORE_CATEGORY_PICKUP,
    },
  },
  setup () {
    return {
      storeStore: useStoreStore(),
    }
  },
  data () {
    return {
      displayInfos: true,
    }
  },
  computed: {
    entry () {
      // Used by storeEntryMixin
      return { categoryType: this.categoryType }
    },
    STORE_CATEGORY_PICKUP: () => STORE_CATEGORY_PICKUP,
    pressInfo () {
      switch (this.press) {
        case STORE_PUBLICITY_AND_STICKER_OPTIONS.YES:
          return this.$t('store.may_referred_to_in_public')
        case STORE_PUBLICITY_AND_STICKER_OPTIONS.NO:
          return this.$t('store.may_not_referred_to_in_public')
        default:
          return this.$t('store.may_referred_to_in_public_unclear')
      }
    },
    collectionQuantity () {
      const matchedWeightType = this.weightTypes.find(type => type.value === this.weightType)
      return matchedWeightType ? matchedWeightType.text : ''
    },
    weightTypes () {
      return this.storeStore.getStoreWeightTypes.map(item => ({ value: item.id, text: item.name }))
    },
    useRegionPickupRules () {
      return this.regionPickupRules === true && this.regionPickupRuleActive === true
    },
  },
  methods: {
    isDateValid (dateString) {
      if (dateString === null) {
        return false
      }

      const date = new Date(dateString)
      return !isNaN(date)
    },
    toggleInfoDisplay () {
      this.displayInfos = !this.displayInfos
    },
  },
}
</script>

<style lang="scss" scoped>
.store-desc {
  font-size: 0.875rem;

  div, p, ul, ol, th, td, label {
    font-size: inherit;
  }

  .desc-block {
    max-width: 100%;
    /* Global fallback */
    overflow-wrap: break-word;
    /* Safari / Edge compat: */
    word-break: break-word;
    /* Desired behavior: */
    overflow-wrap: anywhere;

    ::v-deep .markdown {
      div, ul, ol, th, td, label {
        margin-bottom: 0;
        font-size: inherit;
      }
    }
  }

  .desc-block-title {
    background-color: var(--fs-color-info-200);
    border-radius: var(--border-radius);
    color: var(--fs-color-info-600);
    font-weight: bolder;
    text-align: center;
  }
}
</style>

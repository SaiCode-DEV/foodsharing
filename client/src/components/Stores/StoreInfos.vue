<template>
  <Container
    :title="$i18n('store.info_container')"
    :tag="`store-infos-${storeId}`"
    wrap-content="p-2"
  >
    <div
      v-show="displayInfos"
      class="store-desc"
    >
      <div
        id="inputAdress"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $i18n('store.address') }}
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <div>
            {{ street }} <br>
            {{ postcode }} {{ city }} <br>
            <a :href="$url('map', { storeId: storeId })">
              <i class="fas fa-map-marker-alt" />
              {{ $i18n('store.to_map') }}
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
          {{ $i18n('store.particularities') }}
        </div>
        <Markdown :source="particularitiesDescription" />
      </div>
      <div
        v-if="particularitiesChain"
        id="chainParticularities"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $i18n('store.particularities_chain') }}
          <i
            class="fas fa-info-circle fa-fw"
            :title="$i18n('store.particularities_chain_tooltip')"
          />
        </div>
        <Markdown :source="particularitiesChain" />
      </div>
      <div
        id="inputAverageCollectionQuantity"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $i18n('store.average_collection_quantity') }}
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
          {{ $i18n('store.attribution') }}
        </div>
        {{ pressInfo }}
      </div>
      <div
        v-if="useRegionPickupRules"
        id="inputUseRegionPickupRules"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $i18n('store.regionPickupRules') }}
        </div>
        <span>{{ $i18n('store.useRegionPickupRules') }}</span><br>
        <span>{{ $i18n('store.regionPickupRuleLong', {regionPickupRuleTimespan, regionPickupRuleLimit, regionPickupRuleLimitDay, regionPickupRuleInactive}) }}</span>
      </div>
      <div
        v-if="isDateValid(lastFetchDate)"
        id="inputMyLastPickup"
        class="desc-block mb-1 py-1"
      >
        <div class="desc-block-title mb-2 py-1">
          {{ $i18n('store.my_last_pickup') }}
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
import { STORE_PUBLICITY_AND_STICKER_OPTIONS } from '@/stores/stores'
import { useStoreStore } from '@/stores/store'
import NavigateWithSelector from '@/components/UI/NavigateWithSelector.vue'

export default {
  components: { Markdown, Container, NavigateWithSelector },
  props: {
    particularitiesDescription: {
      type: String,
      default: '',
    },
    particularitiesChain: {
      type: String,
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
    pressInfo () {
      switch (this.press) {
        case STORE_PUBLICITY_AND_STICKER_OPTIONS.YES:
          return this.$i18n('store.may_referred_to_in_public')
        case STORE_PUBLICITY_AND_STICKER_OPTIONS.NO:
          return this.$i18n('store.may_not_referred_to_in_public')
        default:
          return this.$i18n('store.may_referred_to_in_public_unclear')
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

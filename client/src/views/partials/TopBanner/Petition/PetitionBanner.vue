<template>
  <TopBanner
    v-if="showBanner"
    ref="topBanner"
    tag="petition"
    :expand-interval-days="2.5"
    @show="show"
    @hidden="displayValue = 0"
  >
    <h3 class="text-center mb-3" v-text="petitionInfo.title" />

    <b-progress :max="max" class="petition-progress">
      <b-progress-bar
        :value="displayValue"
        variant="success"
        animated
      >
        <span>
          <b class="px-2" v-text="`${value.toLocaleString()} / ${required.toLocaleString()}`" />
          <span v-if="value >= required">🎉</span>
        </span>
      </b-progress-bar>
    </b-progress>

    <p class="text-center mt-2">
      <b v-text="$t(`petition.progressText.${currentProgressStep}`)" />
      (<span v-text="$t('petition.daysLeft', { daysLeft })" />)
    </p>

    <div>
      <!-- eslint-disable-next-line vue/no-v-html -->
      <div class="d-flex gap-4 flex-wrap petition-info" v-html="petitionInfo.body" />
      <b-button
        :href="petitionUrl"
        variant="success"
        class="float-right"
      >
        {{ $t('petition.link') }}
      </b-button>
    </div>
  </TopBanner>
</template>

<script>
import { getPetitionData } from '@/api/petition'
import TopBanner from '../TopBanner.vue'
import { useRegionStore } from '@/stores/regions'
import { getContent } from '@/api/content'

const progressSteps = [500, 2000, 8000, 15000, 20000, 25000, 30000, Infinity]

export default {
  components: { TopBanner },
  setup () {
    const regionStore = useRegionStore()
    return { regionStore }
  },
  data () {
    return {
      value: null,
      daysLeft: null,
      petitionUrl: '',
      displayValue: 0,
      required: 30_000,
      petitionInfo: null,
    }
  },
  computed: {
    max () {
      return Math.max(this.value, this.required)
    },
    currentProgressStep () {
      return progressSteps.find(x => x >= this.value)
    },
    isGerman () {
      const germanyId = 1
      return this.regionStore.findRegion(germanyId) !== null
    },
    showBanner () {
      return Number.isInteger(this.value) && Number.isInteger(this.daysLeft) && this.isGerman
    },
  },
  async mounted () {
    const [petitionsData, content] = await Promise.all([
      await getPetitionData(),
      await getContent(92),
    ])
    if (!petitionsData) return
    this.value = petitionsData.signatures
    this.daysLeft = petitionsData.daysLeft
    this.petitionUrl = petitionsData.href
    this.petitionInfo = content
  },
  methods: {
    async show () {
      await new Promise(resolve => window.setTimeout(resolve, 100))
      this.displayValue = this.value
    },
  },
}
</script>
<style lang="scss">
.progress-bar {
  color: var(--fs-color-black);
  overflow: visible;
  white-space: nowrap;
}
.petition-progress {
  box-shadow: 0 0 6px 1px #0004;
  height: 1.75em;
}
.petition-info{
  gap: 2em;
  p {
    text-align: justify;
    flex: 1 0 0;
    min-width: 20em;
  }
}
</style>

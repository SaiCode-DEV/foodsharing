<template>
  <FoodsharingControllerPageWrapper>
    <div class="container">
      <h4>
        FeatureToggles
      </h4>

      <div class="alert alert-danger mt-2">
        Please be careful, danger-zone! Only change FeatureToggle states if you know what you are doing
        and have technical experience as a developer / admin.
      </div>

      <p v-if="featureToggles.length === 0">
        No feature toggles are defined.
      </p>

      <div
        v-for="featureToggle in featureToggles"
        :key="featureToggle.identifier"
        class="p-3 border bg-light mb-2 row mx-0"
      >
        <div class="col-md-10 col-12">
          <h4 class="text-break">
            {{ featureToggle.identifier }}
            <span
              class="badge badge-secondary"
              :class="[featureToggle.isActive ? 'bg-success' : 'bg-danger']"
            >{{ getToggleStateDescription(featureToggle.isActive) }}
            </span>
          </h4>
        </div>
        <div class="col-md-2">
          <button
            type="button"
            class="btn btn-secondary"
            @click="toggle(featureToggle.identifier)"
          >
            Toggle
          </button>
        </div>
      </div>
    </div>
  </FoodsharingControllerPageWrapper>
</template>

<script>
import { fetchAllFeatureToggles, switchFeatureToggleState } from '@/api/featuretoggles'
import FoodsharingControllerPageWrapper from '@/views/pages/FoodsharingControllerPageWrapper.vue'

export default {
  components: { FoodsharingControllerPageWrapper },
  data () {
    return {
      featureToggles: [],
    }
  },
  async created () {
    await this.fetchAllFeatureToggles()
  },
  methods: {
    getToggleStateDescription (state) {
      return state ? 'on' : 'off'
    },
    async fetchAllFeatureToggles () {
      const response = await fetchAllFeatureToggles()
      this.featureToggles = response.featureToggles
    },
    async toggle (featureToggleIdentifier) {
      await switchFeatureToggleState(featureToggleIdentifier)
      await this.fetchAllFeatureToggles()
    },
  },
}
</script>

<template>
  <div>
    <p>{{ $t('bcard.claim') }}</p>
    <p>{{ $t('bcard.desc') }}</p>
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label>{{ $t('bcard.role') }}:</label>
          <select
            v-model="selectedRole"
            required
          >
            <option
              v-for="role in roles"
              :key="role"
              :value="role"
            >
              {{ getRoleName(role) }}
            </option>
          </select>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-group">
          <label>{{ $t('bcard.region') }}:</label>
          <select
            v-model="selectedRegion"
            :disabled="selectedRole.length === 0"
          >
            <option
              v-for="region in filteredRegions"
              :key="region.id"
              :value="region.id"
            >
              {{ region.name }}
            </option>
          </select>
        </div>
      </div>
    </div>
    <b-button
      :href="$url('createBusinessCard', { role: selectedRole, regionGroupId: selectedRegion })"
      :disabled="selectedRegion.length === 0"
      variant="primary"
    >
      {{ $t('bcard.generate') }}
    </b-button>
  </div>
</template>

<script>

export default {
  name: 'BusinessCard',
  props: {
    businessCardData: { type: Object, required: true },
  },
  data () {
    return {
      selectedRole: [],
      filteredRegions: [],
      selectedRegion: [],
      translation: {
        bot: this.$t('terminology.ambassador.d'),
        fs: this.$t('terminology.foodsaver.d'),
        sm: this.$t('terminology.storemanager.d'),
      },
    }
  },
  computed: {
    roles () {
      const desiredRoles = ['bot', 'fs', 'sm']
      return desiredRoles.filter(role => this.businessCardData[role])
    },
  },
  watch: {
    selectedRole (newVal) {
      if (newVal) {
        this.filteredRegions = this.businessCardData[newVal].map(region => ({
          id: region.id,
          name: region.name,
        }))
      } else {
        this.filteredRegions = []
      }
    },
  },
  methods: {
    getRoleName (role) {
      return this.translation[role] || role
    },
  },
}
</script>

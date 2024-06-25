<template>
  <div>
    <p>{{ $i18n('bcard.claim') }}</p>
    <p>{{ $i18n('bcard.desc') }}</p>
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label>{{ $i18n('bcard.role') }}:</label>
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
          <label>{{ $i18n('bcard.region') }}:</label>
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
      {{ $i18n('bcard.generate') }}
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
        bot: this.$i18n('terminology.ambassador.d'),
        fs: this.$i18n('terminology.foodsaver.d'),
        sm: this.$i18n('terminology.storemanager.d'),
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

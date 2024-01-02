<template>
  <a
    :href="$url('foodsharepoint', foodSharePoint.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        {{ foodSharePoint.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="foodSharePoint.region_id">
          in
          <a :href="$url('foodsharepoints', foodSharePoint.region_id)">
            {{ foodSharePoint.region_name }}
          </a>
        </span>
        <span v-if="foodSharePoint.city">
          <span v-if="foodSharePoint.street">{{ foodSharePoint.street }},</span>
          <span v-if="foodSharePoint.zipCode">{{ foodSharePoint.zipCode }}</span>
          {{ foodSharePoint.city }}
        </span>
      </small>
    </div>
  </a>
</template>
<script>

export default {
  components: { },
  props: {
    foodSharePoint: {
      type: Object,
      required: true,
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: '•';
}
</style>

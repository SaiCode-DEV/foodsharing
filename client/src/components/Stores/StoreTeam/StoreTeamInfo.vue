<template>
  <div class="team-memberinfo">
    <b-tooltip :target="`member-${user.id}`" triggers="hover blur">
      <div v-if="user.isManager">
        {{ $i18n('store.isManager') }}
      </div>
      <div v-if="user.joinDate">
        {{ $i18n('store.memberSince', { date: $dateFormatter.date(user.joinDate, { short: true}) }) }}
      </div>
      <div v-if="user.fetchCount && user.lastPickup">
        {{ $i18n('store.lastPickup', { date: $dateFormatter.date(user.lastPickup, { short: true}) }) }}
      </div>
      <div v-else-if="!user.fetchCount">
        {{ $i18n('store.noPickup') }}
      </div>
      <div v-else-if="user.isJumper">
        {{ $i18n('store.isJumper') }}
      </div>
      <div v-else-if="!user.isVerified">
        {{ $i18n('store.isNotVerified') }}
      </div>
    </b-tooltip>
    <a
      :id="`member-${user.id}`"
      href="#memberdetails"
      class="member-info"
      :class="{'jumper': user.isJumper}"
      @click.prevent="$emit('toggle-details')"
    >
      <span class="member-name">
        {{ user.name }}
      </span>
      <span
        v-if="user.phoneNumberIsValid"
        class="member-phone"
      >
        {{ user.phoneNumber }}
      </span>
      <span
        v-if="user.fetchCount && user.lastPickup"
        :class="[storeManagerView ? 'font-weight-bolder text-black-50' : 'text-muted']"
      >
        {{ storeManagerView ?
          $i18n('store.lastPickup', { date: $dateFormatter.dateBasic(user.lastPickup) }) :
          $i18n('store.lastPickupShort', { date: $dateFormatter.relativeTime(user.lastPickup) }) }}
      </span>
      <span
        v-else-if="user.joinDate && storeManagerView"
        class="text-muted"
      >
        {{ $i18n('store.memberSince', { date: $dateFormatter.dateBasic(user.joinDate) }) }}
      </span>
    </a>
  </div>
</template>

<script>
export default {
  props: {
    user: { type: Object, required: true },
    storeManagerView: { type: Boolean, default: false },
  },
}
</script>

<style lang="scss" scoped>
.member-info {
  display: flex;
  min-height: 50px;
  padding-left: 10px;
  flex-direction: column;
  justify-content: center;
  font-size: smaller;
  color: var(--fs-color-dark);

  &:hover, &:focus {
    text-decoration: none;
    outline-color: var(--fs-color-primary-500);
  }

  .member-name {
    padding-left: 1px;
    min-width: 0;
    word-break: break-word;
    font-weight: bolder;
  }
}
</style>

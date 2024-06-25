<template>
  <b-modal
    id="BananaModal"
    size="xl"
    :cancel-title="$i18n('button.cancel')"
    :title="$i18n('profile.banana.title', { count: (bananaStatistics.bananas ? bananaStatistics.bananas.length : '') })"
  >
    <BananaList
      :recipient-name="bananaStatistics.recipientName"
      :recipient-id="bananaStatistics.recipientId"
      :can-give-banana="bananaStatistics.canGiveBanana"
      :can-remove-banana="bananaStatistics.canRemoveBanana"
      :bananas="bananaStatistics.bananas"
    />
  </b-modal>
</template>

<script>
import BananaList from '@php/Modules/Profile/components/BananaList.vue'
export default {
  name: 'BananaModal',
  components: { BananaList },
  props: { bananaStatistics: { type: Object, required: true } },
}
</script>

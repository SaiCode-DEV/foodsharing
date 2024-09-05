<template>
  <b-modal
    id="BananaModal"
    size="xl"
    :cancel-title="$i18n('button.cancel')"
    :title="$i18n('profile.banana.title', { count: (receivedBananas?.length ?? '') })"
    @show="loadBananas"
  >
    <BananaList
      v-if="receivedBananas"
      :recipient="recipient"
      :can-give-banana="metadata.mayGiveBanana"
      :can-remove-banana="metadata.mayDeleteBananas || isRecipient"
      :bananas="receivedBananas"
    />
  </b-modal>
</template>

<script>
import BananaList from '@php/Modules/Profile/components/BananaList.vue'
import { getReceivedBananas } from '@/api/banana'
import DataUser from '@/stores/user'

export default {
  name: 'BananaModal',
  components: { BananaList },
  props: {
    recipient: { type: Object, required: true },
    metadata: { type: Object, default: null },
  },
  data: () => ({
    receivedBananas: null,
  }),
  computed: {
    isRecipient () {
      return DataUser.getters.getUserId() === this.recipient.id
    },
  },
  methods: {
    async loadBananas () {
      if (!this.receivedBananas) {
        this.receivedBananas = await getReceivedBananas(this.recipient.id)
      }
    },
  },
}
</script>

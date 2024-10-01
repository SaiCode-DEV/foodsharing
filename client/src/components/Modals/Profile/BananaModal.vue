<template>
  <b-modal
    id="BananaModal"
    size="xl"
    :cancel-title="$i18n('button.cancel')"
    :ok-title="$i18n('button.ok')"
    :title="$i18n('profile.banana.title', { count: titleCount })"
    @show="loadBananas"
  >
    <div v-if="!isRecipient">
      <BananaList
        v-if="receivedBananas"
        :recipient="recipient"
        :can-give-banana="metadata.mayGiveBanana"
        :can-remove-banana="metadata.mayDeleteBananas || isRecipient"
        :bananas="receivedBananas"
        :none-placeholder="$i18n(`profile.banana.recieved_none.${isYou}`, { name: recipient.name })"
      />
    </div>
    <b-tabs
      v-else
      content-class="mt-3"
    >
      <b-tab
        v-if="receivedBananas"
        :title="`${$i18n('terminology.received')} (${receivedBananas.length})`"
        active
      >
        <BananaList
          :recipient="recipient"
          :can-give-banana="metadata.mayGiveBanana"
          :can-remove-banana="metadata.mayDeleteBananas || isRecipient"
          :bananas="receivedBananas"
          :none-placeholder="$i18n(`profile.banana.recieved_none.${isYou}`, { name: recipient.name })"
        />
      </b-tab>
      <b-tab
        v-if="isRecipient && sentBananas"
        :title="`${$i18n('terminology.sent')} (${sentBananas.length})`"
      >
        <BananaList
          :recipient="recipient"
          :can-give-banana="false"
          :can-remove-banana="false"
          :bananas="sentBananas"
          :none-placeholder="$i18n(`profile.banana.sent_none.${isYou}`, { name: recipient.name })"
        />
      </b-tab>
    </b-tabs>
  </b-modal>
</template>

<script setup>
import BananaList from '@php/Modules/Profile/components/BananaList.vue'
import { getReceivedBananas, getSentBananas } from '@/api/banana'
import { useUserStore } from '@/stores/user'
import { ref, computed, defineProps } from 'vue'

const userStore = useUserStore()

const props = defineProps({
  recipient: { type: Object, required: true },
  metadata: { type: Object, default: null },
})

const receivedBananas = ref(null)
const sentBananas = ref(null)

const isRecipient = computed(() => userStore.getUserId === props.recipient.id)
const isYou = computed(() => isRecipient.value ? 'you' : 'other')
const titleCount = computed(() => {
  if (isRecipient.value) { return '' }
  return receivedBananas.value?.length ?? ''
})

const loadBananas = async () => {
  if (!receivedBananas.value || !sentBananas.value) {
    getReceivedBananas(props.recipient.id).then((bananas) => {
      receivedBananas.value = bananas
    })
  }

  if (isRecipient.value && !sentBananas.value) {
    getSentBananas(props.recipient.id).then((bananas) => {
      sentBananas.value = bananas
    })
  }
}

</script>

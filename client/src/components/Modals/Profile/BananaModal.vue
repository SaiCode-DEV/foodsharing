<template>
  <b-modal
    id="BananaModal"
    size="xl"
    :ok-only="true"
    :ok-title="$i18n('button.ok')"
    :title="$i18n('profile.banana.title', { count: titleCount })"
    @show="loadBananas"
  >
    <div v-if="!isRecipient">
      <BananaList
        v-if="receivedBananas"
        :recipient="recipient"
        :can-give-banana="metadata.mayGiveBanana"
        :can-remove-banana="metadata.mayDeleteBananas"
        :bananas="receivedBananas"
        :none-placeholder="$i18n(`profile.banana.recieved_none.${isYou}`, { name: recipient.name })"
        @bananas-updated="bananasUpdated"
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
          can-remove-banana
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
          :can-remove-banana="isRecipient"
          :bananas="sentBananas"
          :none-placeholder="$i18n(`profile.banana.sent_none.${isYou}`, { name: recipient.name })"
          is-sent
          @bananas-updated="bananasUpdated"
        />
      </b-tab>
    </b-tabs>
  </b-modal>
</template>

<script setup>
import BananaList from '@php/Modules/Profile/components/BananaList.vue'
import { getReceivedBananas, getSentBananas } from '@/api/banana'
import { useUserStore } from '@/stores/user'
import { ref, computed, defineProps, defineEmits } from 'vue'

const userStore = useUserStore()

const props = defineProps({
  recipient: { type: Object, required: true },
  metadata: { type: Object, default: null },
})
const emit = defineEmits(['bananas-updated'])

const updateNeeded = ref(true)
const receivedBananas = ref(null)
const sentBananas = ref(null)
const bananasUpdated = (bananaCount, mayGiveBanana) => {
  updateNeeded.value = true
  emit('bananas-updated', bananaCount, mayGiveBanana)
}

const isRecipient = computed(() => userStore.getUserId === props.recipient.id)
const isYou = computed(() => isRecipient.value ? 'you' : 'other')
const titleCount = computed(() => {
  if (isRecipient.value) { return '' }
  return receivedBananas.value?.length ?? ''
})

const loadBananas = async () => {
  if (updateNeeded.value && !receivedBananas.value) {
    getReceivedBananas(props.recipient.id).then((bananas) => {
      receivedBananas.value = bananas
    })
  }

  if (updateNeeded.value && isRecipient.value) {
    getSentBananas(props.recipient.id).then((bananas) => {
      sentBananas.value = bananas
    })
  }
  updateNeeded.value = false
}

</script>

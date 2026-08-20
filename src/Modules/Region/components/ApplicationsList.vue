<template>
  <div>
    <Container
      :title="$t('group.applications_for', { name: props.groupName }) + ` (${activeApplications.length})`"
      :collapsible="false"
    >
      <div
        v-for="application in activeApplications"
        :key="application.applicant.id"
        class="list-group-item py-0"
      >
        <div v-b-toggle="`expand-application-${application.applicant.id}`" class="application-header py-2 d-flex align-items-center">
          <Avatar :user="application.applicant" class="mr-2" />
          <b v-text="application.applicant.name" />
          <i class="fas fa-angle-down ml-auto" />
        </div>

        <b-collapse
          :id="`expand-application-${application.applicant.id}`"
          class="mb-2"
          accordion="group-application"
          :visible="application.applicant.id === userId"
        >
          <Markdown :source="application.applicationText" class="mt-3" />
          <div class="mt-3">
            <b-button variant="danger" @click="declineApplication(application)">
              <i class="fas fa-user-slash mr-1" />
              {{ $t('group.apply.deny') }}
            </b-button>
            <b-button variant="primary" @click="acceptApplication(application)">
              <i class="fas fa-user-plus mr-1" />
              {{ $t('group.apply.accept') }}
            </b-button>
          </div>
        </b-collapse>
      </div>
    </Container>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import i18n from '@/helper/i18n'
import Avatar from '@/components/Avatar/Avatar.vue'
import Container from '@/components/Container/Container.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import { GET, pulseError, pulseInfo } from '@/script'
import * as api from '@/api/applications'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

const { confirmationDialogue } = useConfirmationDialogue()

const props = defineProps({
  applications: { type: Array, required: true },
  groupName: { type: String, required: true },
  groupId: { type: Number, required: true },
})

const activeApplications = ref(props.applications)
const userId = ref(parseInt(GET('userId')))

watch(() => props.applications, (newApplications) => {
  activeApplications.value = newApplications
})

function removeApplicationFromList (application) {
  const index = activeApplications.value.indexOf(application)
  if (index >= 0) {
    activeApplications.value.splice(index, 1)
  }
}

async function declineApplication (application) {
  if (!await confirmationDialogue('group.confirm_decline', {
    okTitle: i18n('terminology.yes'),
  })) return
  try {
    await api.declineApplication(props.groupId, application.applicant.id)
    removeApplicationFromList(application)
    pulseInfo(i18n('group.apply.declined'))
  } catch {
    pulseError(i18n('error_unexpected'))
  }
}

async function acceptApplication (application) {
  try {
    await api.acceptApplication(props.groupId, application.applicant.id)
    removeApplicationFromList(application)
    pulseInfo(i18n('group.apply.accepted'))
  } catch {
    pulseError(i18n('error_unexpected'))
  }
}
</script>

<style lang="scss" scoped>
.application-header.not-collapsed i.fas {
  transform: rotate(180deg);
}

.application-header i.fas {
  transition: transform 0.35s ease;
}
</style>

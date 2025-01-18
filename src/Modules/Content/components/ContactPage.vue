<template>
  <Container
    :hide-header="true"
    :wrap-content="true"
    :collapsible="false"
  >
    <b-container class="py-5">
      <b-row class="justify-content-center mb-5">
        <b-col md="8">
          <p class="text-center lead">
            {{ $i18n('contact_page.description') }}
          </p>
        </b-col>
      </b-row>

      <b-row>
        <ContactCard
          v-for="(card, index) in contactCards"
          :key="index"
          :icon="card.icon"
          :title="$i18n(card.titleKey)"
          :button-text="$i18n(card.buttonTextKey)"
          :button-href="card.href"
          :button-target="card.target"
          @click="card.isModal ? $bvModal.show('contact-modal') : null"
        >
          {{ $i18n(card.textKey) }}
        </ContactCard>
      </b-row>
    </b-container>

    <b-modal
      id="contact-modal"
      :title="$i18n('contact_page.common.modal.title')"
      hide-footer
    >
      <Markdown
        :source="$i18n('contact_page.common.modal.text', {
          common_mail: urls.contact_email_common(),
          common_mail_ch: urls.contact_email_common_ch(),
        })"
      />
    </b-modal>
  </Container>
</template>

<script setup>
import { ref } from 'vue'
import Container from '@/components/Container/Container.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import ContactCard from './ContactCard.vue'
import { urls } from '@/helper/urls'

const contactCards = ref([
  {
    icon: 'fa-question-circle',
    titleKey: 'contact_page.support.title',
    buttonTextKey: 'contact_page.support.link',
    textKey: 'contact_page.support.text',
    href: urls.helpdesk(),
    target: '_blank',
  },
  {
    icon: 'fa-users',
    titleKey: 'contact_page.regions.title',
    buttonTextKey: 'contact_page.regions.link',
    textKey: 'contact_page.regions.text',
    href: urls.communities(),
  },
  {
    icon: 'fa-envelope',
    titleKey: 'contact_page.common.title',
    buttonTextKey: 'contact_page.common.link',
    textKey: 'contact_page.common.text',
    isModal: true,
  },
])
</script>

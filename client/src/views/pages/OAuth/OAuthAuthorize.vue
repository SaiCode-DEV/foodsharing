<template>
  <b-container class="py-4">
    <b-row class="justify-content-center">
      <b-col
        cols="12"
        lg="8"
      >
        <b-alert
          v-if="error"
          show
          variant="danger"
        >
          <Markdown :source="getErrorMessage()" />
          <small class="mt-2"> {{ error }}</small>
          <pre v-if="hint" class="mt-2"><code>{{ hint }}</code></pre>
        </b-alert>
        <b-card v-else body-class="p-4">
          <header class="mb-4">
            <h1 class="h4 mb-2">
              {{ $t('oauth.authorize.title', { clientName }) }}
            </h1>
          </header>

          <section class="mb-4">
            <h2 class="h6 text-muted mb-2">
              {{ $t('oauth.authorize.requested_permissions') }}
            </h2>
            <b-list-group>
              <b-list-group-item
                v-for="scope in cleanScopes"
                :key="scope"
              >
                <div class="d-flex flex-column">
                  <span>
                    <strong>{{ scopeTitle(scope) }}</strong>
                  </span>
                  <Markdown :source="scopeDescription(scope)" class="text-muted" />
                </div>
              </b-list-group-item>
            </b-list-group>
          </section>

          <b-alert
            v-if="redirectUri"
            show
            variant="info"
            class="mb-4"
          >
            <small>{{ $t('oauth.authorize.redirect_notice', { redirectUrl: redirectUri }) }}</small>
          </b-alert>

          <b-form :action="actionUrl" method="post">
            <input
              type="hidden"
              name="_csrf_token"
              :value="csrfToken"
            >

            <b-form-group :label="$t('oauth.remember.title')" label-for="remember-choice">
              <b-form-checkbox
                id="remember-choice"
                v-model="rememberChoice"
                name="remember"
                value="1"
              >
                {{ $t('oauth.remember.md', { clientName }) }}
              </b-form-checkbox>
            </b-form-group>

            <div class="d-flex flex-column flex-md-row gap-2">
              <b-button
                type="submit"
                name="decision"
                value="approve"
                variant="success"
                class="mb-2 mb-md-0 mr-md-2"
              >
                {{ $t('oauth.action.authorize') }}
              </b-button>
              <b-button
                type="submit"
                name="decision"
                value="deny"
                variant="outline-secondary"
              >
                {{ $t('oauth.action.deny') }}
              </b-button>
            </div>
          </b-form>
        </b-card>
      </b-col>
    </b-row>
  </b-container>
</template>

<script setup>
import { ref, defineProps, computed } from 'vue'
import i18n from '@/helper/i18n'
import Markdown from '@/components/Markdown/Markdown.vue'

const props = defineProps({
  clientName: { type: String, required: true },
  clientIdentifier: { type: String, required: true },
  scopes: {
    type: Array,
    default: () => [],
  },
  actionUrl: { type: String, required: true },
  redirectUri: { type: String, default: null },
  csrfToken: { type: String, required: true },
  error: { type: String, default: null },
  hint: { type: String, default: null },
})

const rememberChoice = ref(true)

const cleanScopes = computed(() => {
  const uniqueScopes = new Set(props.scopes)
  return Array.from(uniqueScopes)
})

function scopeTitle (scope) {
  return i18n(`oauth.scopes.${scope}.title`) || scope
}

function scopeDescription (scope) {
  if (i18n(`oauth.scopes.${scope}.md`) !== `oauth.scopes.${scope}.md`) {
    return i18n(`oauth.scopes.${scope}.md`)
  }
  return ''
}

function getErrorMessage () {
  if (props.error.startsWith('oauth.error.')) {
    return i18n(props.error, { clientName: props.clientName, clientId: props.clientIdentifier })
  }
  return i18n('oauth.error.unknown_error')
}
</script>

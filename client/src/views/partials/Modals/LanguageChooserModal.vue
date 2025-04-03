<template>
  <b-modal
    id="languageChooserModal"
    ref="languageChooserModal"
    :title="$i18n('language_chooser.title')"
    :cancel-title="$i18n('button.cancel')"
    :ok-title="$i18n('language_chooser.choose_button')"

    @show="fetchLanguages"
    @ok="changeLanguage"
  >
    {{ $i18n('language_chooser.content') }}
    <div
      v-if="loading"
      class="loader-container mx-auto"
    >
      <i class="fas fa-spinner fa-spin" />
    </div>
    <b-form-select
      v-else
      v-model="language"
      :options="languages"
      text="Dropdown Button"
    />

    <p class="mt-2">
      <a
        href="https://hosted.weblate.org/projects/foodsharing/#languages"
        target="_blank"
        rel="noopener noreferrer"
      >{{ $i18n('language_chooser.translation_help') }}
      </a>.
      <br>
      <small><i class="fas fa-info-circle" /> {{ $i18n('language_chooser.translation_help_info_text') }}</small>
    </p>
  </b-modal>
</template>

<script>
import { pulseError } from '@/script'
import { getLocale, setLocale } from '@/api/locale'

export default {
  name: 'LanguageChooserModal',
  data () {
    return {
      language: null,
      languages: [
        { value: 'de', text: 'Deutsch' },
        { value: 'en', text: 'English' },
        { value: 'es', text: 'Español' },
        { value: 'fr', text: 'Français' },
        { value: 'it', text: 'Italiano' },
        { value: 'nb_NO', text: 'Norsk (Bokmål)' },
        { value: 'tr', text: 'Türkçe' },
      ],
      loading: true,
    }
  },
  methods: {
    async fetchLanguages () {
      this.loading = true
      try {
        this.language = await getLocale()
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }

      this.loading = false
    },
    async changeLanguage () {
      try {
        await setLocale(this.language)
        setTimeout(() => {
          window.location.reload()
        }, 25)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
  },
}
</script>

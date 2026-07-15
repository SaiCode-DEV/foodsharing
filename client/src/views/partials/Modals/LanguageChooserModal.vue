<template>
  <b-modal
    id="languageChooserModal"
    ref="languageChooserModal"
    :title="$t('language_chooser.title')"
    :cancel-title="$t('button.cancel')"
    :ok-title="$t('language_chooser.choose_button')"

    @show="fetchLanguages"
    @ok="changeLanguage"
  >
    {{ $t('language_chooser.content') }}
    <div
      v-if="loading"
      class="loader-container mx-auto"
    >
      <i class="fas fa-spinner fa-spin" />
    </div>
    <b-form-select
      v-else
      v-model="language"
      :options="languageOptions"
      text="Dropdown Button"
      @change="updatePercentage"
    />
    <b-alert
      v-if="percentageTranslated !== null && percentageTranslated < 100"
      class="mt-2"
      variant="warning"
      show
    >
      {{ $t('language_chooser.untranslated_hint', { percentage: percentageTranslated }) }}
    </b-alert>

    <p class="mt-2">
      <a
        href="https://hosted.weblate.org/projects/foodsharing/#languages"
        target="_blank"
        rel="noopener noreferrer"
      >{{ $t('language_chooser.translation_help') }}
      </a>.
      <br>
      <small><i class="fas fa-info-circle" /> {{ $t('language_chooser.translation_help_info_text') }}</small>
    </p>
  </b-modal>
</template>

<script>
import { pulseError } from '@/script'
import { getLocale, getLocales, setLocale } from '@/api/locale'
import { hasLocale } from '@/helper/i18n'

export default {
  name: 'LanguageChooserModal',
  data () {
    return {
      language: null,
      languages: [],
      languageOptions: [],
      percentageTranslated: null,
      loading: true,
    }
  },
  methods: {
    async fetchLanguages () {
      this.loading = true
      try {
        this.languages = await getLocales()

        // To prevent errors, filter out languages that do not exist as a file on client-side
        this.languages = this.languages.filter(
          language => hasLocale(language.languageCode),
        )

        // languageOptions contains the languages as a list of objects required by the form-select
        this.languageOptions = this.languages.map(lang => {
          return {
            value: lang.languageCode,
            text: `${lang.name} / ${lang.englishName}` + (lang.percentageTranslated !== null ? ` (${lang.percentageTranslated}%)` : ''),
          }
        }).sort((a, b) => a.text.localeCompare(b.text))

        this.language = await getLocale()
        this.updatePercentage()
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      }

      this.loading = false
    },
    async changeLanguage () {
      try {
        await setLocale(this.language)
        setTimeout(() => {
          window.location.reload()
        }, 250)
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      }
    },
    updatePercentage () {
      if (this.language !== null) {
        const entry = this.languages.find(lang => lang.languageCode === this.language)
        this.percentageTranslated = entry?.percentageTranslated
      } else {
        this.percentageTranslated = null
      }
    },
  },
}
</script>

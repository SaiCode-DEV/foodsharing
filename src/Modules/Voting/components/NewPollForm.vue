<template>
  <div class="bootstrap">
    <div class="card rounded">
      <div class="card-header text-white bg-primary">
        <span v-if="poll">{{ $t('poll.edit.title') }}</span>
        <span v-else>{{ $t('poll.new_poll.title') }}<span v-if="region"> in {{ region.name }}</span></span>
      </div>
      <b-form
        :class="{disabledLoading: isLoading, 'card-body': true}"
        @submit="showConfirmDialog"
      >
        <b-alert
          show
          variant="dark"
        >
          {{ $t('polls.hint_2') }}: <a :href="$url('wiki_voting')">{{ $url('wiki_voting') }}</a>
        </b-alert>
        <b-form-group
          :label="$t('poll.new_poll.name')"
          label-for="input-name"
          class="mb-4"
        >
          <b-form-input
            id="input-name"
            v-model="v$.name.$model"
            trim
            :state="v$.name.$error ? false : null"
          />
          <div v-if="v$.name.$error" class="invalid-feedback">
            {{ $t('poll.new_poll.name_required') }}
          </div>
        </b-form-group>

        <b-form-group v-if="!poll" class="mb-3">
          <template #label>
            {{ $t('poll.new_poll.scope') }}
            <Info info-key="pollScopes" />
          </template>
          <b-form-radio
            v-for="index in possibleScopes"
            :key="index"
            v-model="scope"
            :value="index"
          >
            {{ $t(`poll.scope_description_${index}`) }}
            ({{ usersPerScope[index] }})
          </b-form-radio>
        </b-form-group>

        <b-form-group
          v-if="!poll"
          :label="$t('poll.new_poll.type')"
          class="mb-4"
        >
          <b-form-radio
            v-for="index in 4"
            :key="index"
            v-model="type"
            :value="index - 1"
            @input="forceUpdateNumberOfOptions"
          >
            {{ $t('poll.type_description_' + (index - 1)) }}
          </b-form-radio>
        </b-form-group>

        <b-form-group v-if="!poll" class="mb-3 datepicker">
          <b-form-row>
            <b-col>
              <label for="input-startdate">{{ $t('poll.new_poll.start_date') }}</label>
            </b-col>
            <b-col class="text-center">
              <label for="input-startdatetime">{{ $t('poll.new_poll.time') }}</label>
            </b-col>
          </b-form-row>
          <b-form-row class="ml-1">
            <b-col>
              <b-form-datepicker
                id="input-startdate"
                v-model="startDate"
                today-button
                class="mb-2"
                v-bind="labelsCalendar || {}"
                :locale="locale"
                :min="new Date()"
                :state="v$.startDateTime.$error ? false : null"
                @input="updateDateStartTimes"
              />
            </b-col>
            <b-col>
              <b-form-timepicker
                id="input-startdatetime"
                v-model="startTime"
                :locale="locale"
                v-bind="labelsTimepicker || {}"
                :state="v$.startDateTime.$error ? false : null"
                @input="updateDateStartTimes"
              />
            </b-col>
          </b-form-row>
          <div
            v-if="v$.startDateTime.$error"
            class="invalid-feedback"
          >
            {{ $t('poll.new_poll.start_date_required') }}
          </div>
        </b-form-group>
        <b-form-group
          v-if="!poll"
          :label="$t('poll.new_poll.end_date')"
          class="mb-3 datepicker"
        >
          <b-form-row class="ml-2">
            <b-col>
              <b-form-datepicker
                id="input-enddate"
                v-model="endDate"
                class="mb-2"
                v-bind="labelsCalendar || {}"
                :locale="locale"
                :min="startDate"
                :state="v$.endDateTime.$error ? false : null"
                @input="updateDateEndTimes"
              />
            </b-col>
            <b-col>
              <b-form-timepicker
                id="input-enddatetime"
                v-model="endTime"
                :locale="locale"
                v-bind="labelsTimepicker || {}"
                :state="v$.endDateTime.$error ? false : null"
                @input="updateDateEndTimes"
              />
            </b-col>
          </b-form-row>
          <div
            v-if="v$.endDateTime.$error"
            class="invalid-feedback"
          >
            {{ $t('poll.new_poll.end_date_required') }}
          </div>
        </b-form-group>

        <b-form-group
          :label="$t('poll.new_poll.description')"
          class="mb-4"
        >
          <MarkdownInput
            :rows="5"
            :value="v$.description.$model"
            :state="v$.description.$error ? false : null"
            :placeholder="$t('poll.new_poll.description_placeholder')"
            :draft-storage-id="'poll-description-' + pollRegionId"
            :region-id="pollRegionId"
            @update:value="newValue => v$.description.$model = newValue"
          />
          <div
            v-if="v$.description.$error"
            class="invalid-feedback"
          >
            {{ $t('poll.new_poll.description_required') }}
          </div>
        </b-form-group>

        <b-form-group
          :label="$t('poll.new_poll.options')"
          label-for="input-name"
          class="mb-4"
        >
          <b-form-row>
            <b-form-spinbutton
              id="input-num-options"
              v-model="numOptions"
              :min="minNumberOfOptions"
              max="200"
              class="m-1 mb-3 mr-5"
              style="width:120px"
              size="sm"
            />
            <b-form-checkbox
              id="shuffle-options-checkbox"
              v-model="shuffleOptions"
              class="mt-2 mb-3 ml-2"
            >
              {{ $t('poll.new_poll.shuffle_options') }}
            </b-form-checkbox>
          </b-form-row>

          <b-form-row
            v-for="index in numOptions"
            :key="index"
            class="row"
          >
            <b-col
              cols="3"
              align-v="stretch"
            >
              {{ $t('poll.new_poll.option') }} {{ index }}:
            </b-col>
            <b-col>
              <b-form-input
                id="input-option-0"
                v-model="v$.options.$model[index-1]"
                trim
                :state="v$.options.$error ? false : null"
                :maxlength="maxOptionLength"
                class="mr-3 mb-1"
              />
            </b-col>
          </b-form-row>
          <div v-if="v$.options.$error" class="invalid-feedback">
            {{ $t('poll.new_poll.option_texts_required') }}
          </div>
        </b-form-group>

        <b-button
          type="submit"
          variant="primary"
          :disabled="v$.$invalid"
        >
          {{ poll ? $t('poll.edit.submit') : $t('poll.new_poll.submit') }}
        </b-button>
        <div v-if="v$.$invalid" class="invalid-feedback">
          {{ $t('poll.new_poll.missing_fields') }}
        </div>
      </b-form>
    </div>
    <b-modal
      v-if="!isLoading"
      ref="pollConfirmModal"
      :title="poll ? $t('poll.edit.title') : $t('poll.new_poll.submit')"
      :cancel-title="$t('button.cancel')"
      :ok-title="poll ? $t('poll.edit.submit') : $t('poll.new_poll.submit')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="submitPoll"
    >
      {{ poll ? $t('poll.edit.submit_question') : $t('poll.new_poll.submit_question') }}
    </b-modal>
  </div>
</template>

<script>
import { createPoll, editPoll } from '@/api/voting'
import { pulseError } from '@/script'
import { navigate } from '@/helper/router'
import dataFormatter from '@/helper/date-formatter'
import i18n, { locale } from '@/helper/i18n'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength } from '@vuelidate/validators'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import { VOTING_TYPE, MAX_OPTION_LENGTH } from '@/stores/polls'
import Info from '@/components/Help/Info.vue'

const EDIT_TIME_HOURS = 1
const DEFAULT_START_TIME_HOURS = 2

function isAfterStart (dateTime) {
  return dateTime > this.startDateTime
}

function isAfterEditTime (dateTime) {
  return dateTime > this.editDateTime
}

// returns if the array does not contain duplicate entries
function areEntriesUnique (array) {
  const unique = [...new Set(array)]
  return unique.length === array.length
}

export default {
  components: { MarkdownInput, Info },
  props: {
    region: {
      type: Object,
      required: false,
      default: null,
    },
    isWorkGroup: {
      type: Boolean,
      required: false,
      default: false,
    },
    usersPerScope: {
      type: Array,
      default: () => [],
    },
    // optional: when provided, component works in edit mode
    poll: {
      type: Object,
      required: false,
      default: null,
    },
  },
  setup () {
    return {
      v$: useVuelidate(),
    }
  },
  data () {
    return {
      isLoading: false,
      name: '',
      scope: 0,
      type: 0,
      startDate: null,
      startTime: null,
      editDateTime: new Date(new Date().getTime() + EDIT_TIME_HOURS * 60 * 60 * 1000),
      endDate: null,
      endTime: null,
      description: '',
      numOptions: 3,
      shuffleOptions: true,
      options: Array(3).fill(''),
      maxOptionLength: null,
      locale,
      labelsTimepicker: {
        labelHours: i18n('timepicker.labelHours'),
        labelMinutes: i18n('timepicker.labelMinutes'),
        labelSeconds: i18n('timepicker.labelSeconds'),
        labelIncrement: i18n('timepicker.labelIncrement'),
        labelDecrement: i18n('timepicker.labelDecrement'),
        labelSelected: i18n('timepicker.labelSelected'),
        labelNoTimeSelected: i18n('timepicker.labelNoTimeSelected'),
        labelCloseButton: i18n('timepicker.labelCloseButton'),
      },
      labelsCalendar: {
        labelPrevYear: i18n('calendar.labelPrevYear'),
        labelPrevMonth: i18n('calendar.labelPrevMonth'),
        labelCurrentMonth: i18n('calendar.labelCurrentMonth'),
        labelNextMonth: i18n('calendar.labelNextMonth'),
        labelNextYear: i18n('calendar.labelNextYear'),
        labelToday: i18n('calendar.labelToday'),
        labelSelected: i18n('calendar.labelSelected'),
        labelNoDateSelected: i18n('calendar.labelNoDateSelected'),
        labelCalendar: i18n('calendar.labelCalendar'),
        labelNav: i18n('calendar.labelNav'),
        labelHelp: i18n('calendar.labelHelp'),
        labelTodayButton: i18n('calendar.labelToday'),
      },
    }
  },
  // Use a function so we can return different validation rules for new vs edit mode
  validations () {
    const base = {
      name: { required, minLength: minLength(1) },
      description: { required, minLength: minLength(1) },
      options: {
        required,
        $each: {
          required,
          minLength: minLength(1),
        },
        areEntriesUnique,
      },
    }

    // If we're creating a new poll, require start/end date/time
    if (!this.poll) {
      return Object.assign({}, base, {
        startDateTime: { required, isAfterEditTime },
        endDateTime: { required, isAfterStart },
      })
    }

    // Edit mode: only validate base fields
    return base
  },
  computed: {
    startDateTime () {
      return new Date(Date.parse(this.startDate + ' ' + this.startTime))
    },
    endDateTime () {
      return new Date(Date.parse(this.endDate + ' ' + this.endTime))
    },
    possibleScopes () {
      if (this.isWorkGroup) {
        // 'store managers' and 'users with home region' does not make sense in work groups
        return [0, 1]
      } else {
        return [0, 1, 2, 3, 4]
      }
    },
    formattedEditTime () {
      return dataFormatter.time(this.editDateTime)
    },
    minNumberOfOptions () {
      // In edit mode, type might be undefined; fall back to stored poll.type
      const t = (this.poll && this.poll.type !== undefined) ? this.poll.type : this.type
      return (t === VOTING_TYPE.THUMB_VOTING || t === VOTING_TYPE.SCORE_VOTING) ? 1 : 2
    },
    pollRegionId () {
      return this.poll?.regionId ?? this.region?.id
    },
  },
  watch: {
    /**
     * When the number of options changes, the options array must be assigned with a new object for the validation to
     * work.
     */
    numOptions () {
      const newOptions = Array(this.numOptions).fill('')
      for (let i = 0; i < Math.min(this.options.length, this.numOptions); i++) {
        newOptions[i] = this.options[i]
      }
      this.options = newOptions
      this.v$.options.$touch()
    },
  },
  mounted () {
    // Prefill for new poll
    const defaultStart = new Date(new Date().getTime() + DEFAULT_START_TIME_HOURS * 60 * 60 * 1000)
    if (!this.poll) {
      this.startDate = defaultStart.toISOString().split('T')[0]
      this.startTime = dataFormatter.time(defaultStart)
    }

    // Prefill from poll when editing
    if (this.poll) {
      this.name = this.poll.name
      this.description = this.poll.description
      this.numOptions = this.poll.options.length
      this.options = this.poll.options.map(x => x.text)
      this.maxOptionLength = MAX_OPTION_LENGTH
      this.shuffleOptions = this.poll.shuffleOptions
      // When editing, keep scope/type/start/end as-is (creation-only fields are hidden)
    } else {
      this.maxOptionLength = null
    }
  },
  methods: {
    updateDateStartTimes () {
      this.v$.startDateTime.$touch()
    },
    updateDateEndTimes () {
      this.v$.endDateTime.$touch()
    },
    forceUpdateNumberOfOptions () {
      // When the poll type changes, the minimal number of options might have changed
      this.numOptions = Math.max(this.numOptions, this.minNumberOfOptions)
    },
    showConfirmDialog (e) {
      e.preventDefault()
      // unified modal ref name
      this.$refs.pollConfirmModal.show()
    },
    async submitPoll (e) {
      e.preventDefault()
      this.isLoading = true
      try {
        if (this.poll) {
          // edit mode
          await editPoll(this.poll.id, this.name, this.description.trim(), this.options, this.shuffleOptions)
          navigate(this.$url('poll', this.poll.id))
        } else {
          // create mode
          const poll = await createPoll(this.region.id, this.name, this.description.trim(), this.startDateTime, this.endDateTime, this.scope, this.type, this.options, this.shuffleOptions, true)
          navigate(this.$url('poll', poll.id))
        }
      } catch (e) {
        pulseError(i18n('error_unexpected') + ': ' + e.message)
      }

      this.isLoading = false
    },
  },
}
</script>

<style lang="scss" scoped>
#input-num-options {
  width: 120px;
}

// Override weird .form-control height styling from bootstrap-theme
// See https://gitlab.com/foodsharing-dev/foodsharing/-/issues/975
.datepicker ::v-deep .b-form-time-control .form-control.b-form-spinbutton.flex-column {
  height: auto;
}

.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>

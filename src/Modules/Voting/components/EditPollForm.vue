<template>
  <div class="bootstrap">
    <div class="card rounded">
      <div class="card-header text-white bg-primary">
        {{ $i18n('poll.new_poll.title') }}
      </div>
      <b-form
        :class="{disabledLoading: isLoading, 'card-body': true}"
        @submit="showConfirmDialog"
      >
        <b-form-group
          :label="$i18n('poll.new_poll.name')"
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
            {{ $i18n('poll.new_poll.name_required') }}
          </div>
        </b-form-group>

        <b-form-group
          :label="$i18n('poll.new_poll.description')"
          class="mb-4"
        >
          <MarkdownInput
            :rows="5"
            :value="v$.description.$model"
            :state="v$.description.$error ? false : null"
            :placeholder="$i18n('poll.new_poll.description_placeholder')"
            @update:value="newValue => v$.description.$model = newValue"
          />
          <div
            v-if="v$.description.$error"
            class="invalid-feedback"
          >
            {{ $i18n('poll.new_poll.description_required') }}
          </div>
        </b-form-group>

        <b-form-group
          :label="$i18n('poll.new_poll.options')"
          label-for="input-name"
          class="mb-4"
        >
          <b-form-spinbutton
            id="input-num-options"
            v-model="numOptions"
            :min="minNumberOfOptions"
            max="200"
            class="m-1 mb-3 mr-3"
            style="width:120px"
            size="sm"
          />

          <b-form-row
            v-for="index in numOptions"
            :key="index"
            class="row"
          >
            <b-col
              cols="3"
              align-v="stretch"
            >
              {{ $i18n('poll.new_poll.option') }} {{ index }}:
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
            {{ $i18n('poll.new_poll.option_texts_required') }}
          </div>
        </b-form-group>

        <b-button
          type="submit"
          variant="primary"
          :disabled="v$.$invalid"
        >
          {{ $i18n('poll.new_poll.submit') }}
        </b-button>
        <div v-if="v$.$invalid" class="invalid-feedback">
          {{ $i18n('poll.new_poll.missing_fields') }}
        </div>
      </b-form>
    </div>

    <b-modal
      v-if="!isLoading"
      ref="editPollConfirmModal"
      :title="$i18n('poll.new_poll.submit')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.send')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="submitPoll"
    >
      {{ $i18n('poll.edit.submit_question') }}
    </b-modal>
  </div>
</template>

<script>
import { editPoll } from '@/api/voting'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength } from '@vuelidate/validators'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import { VOTING_TYPE, MAX_OPTION_LENGTH } from '@/stores/polls'

// returns if the array does not contain duplicate entries
function areEntriesUnique (array) {
  const unique = [...new Set(array)]
  return unique.length === array.length
}

export default {
  components: { MarkdownInput },
  props: {
    poll: {
      type: Object,
      required: true,
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
      name: this.poll.name,
      description: this.poll.description,
      numOptions: this.poll.options.length,
      options: this.poll.options.map(x => x.text),
      maxOptionLength: MAX_OPTION_LENGTH,
    }
  },
  validations: {
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
  },
  computed: {
    minNumberOfOptions () {
      return (this.type === VOTING_TYPE.THUMB_VOTING || this.type === VOTING_TYPE.SCORE_VOTING) ? 1 : 2
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
  methods: {
    showConfirmDialog (e) {
      e.preventDefault()
      this.$refs.editPollConfirmModal.show()
    },
    async submitPoll (e) {
      e.preventDefault()
      this.isLoading = true
      try {
        await editPoll(this.poll.id, this.name, this.description.trim(), this.options)
        window.location = this.$url('poll', this.poll.id)
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

.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>

<template>
  <div class="container bootstrap">
    <div class="card mb-3 rounded">
      <div class="card-header text-white bg-primary">
        {{ $i18n('group.edit.title', { group: group.name }) }}
      </div>
      <div class="card-body">
        <b-form
          @submit="submit"
        >
          <b-form-group
            :label="$i18n('group.name')"
            label-for="input-name"
            class="mb-4"
          >
            <b-form-input
              id="input-name"
              v-model="$v.name.$model"
              trim
              :state="$v.name.$error ? false : null"
            />
            <div v-if="$v.name.$error" class="invalid-feedback">
              {{ $i18n('group.edit.name_required') }}
            </div>
          </b-form-group>

          <b-form-group
            :label="$i18n('group.description')"
            class="mb-4"
          >
            <MarkdownInput
              ref="md-input"
              variant="outline-primary"
              :placeholder="$i18n('group.edit.description_placeholder')"
              :rows="2"
              :conceal-toolbar="true"
              :value="description"
              :region-id="group.id"
              @update:value="newValue => description = newValue"
            />
            <div
              v-if="$v.description.$error"
              class="invalid-feedback"
            >
              {{ $i18n('group.edit.description_required') }}
            </div>
          </b-form-group>

          <b-form-group
            :label="$i18n('group.photo')"
            class="mb-4"
          >
            <file-upload
              :filename="validFileName"
              :is-image="true"
              :img-width="600"
              :img-height="400"
              @change="onPhotoChange"
            />
          </b-form-group>

          <b-form-group
            :label="$i18n('group.applications')"
            class="mb-4"
          >
            <b-form-select
              id="input-application-requirement"
              v-model="apply_type"
              :options="apply_type_options"
            />
          </b-form-group>

          <b-form-group
            v-if="applyDetailsVisible"
            :label="$i18n('group.application_requirements.banana_count')"
            class="mb-4"
          >
            <b-form-spinbutton
              id="input-required-bananas"
              v-model="required_bananas"
              min="0"
              max="20"
              inline
            />
          </b-form-group>

          <b-form-group
            v-if="applyDetailsVisible"
            :label="$i18n('group.application_requirements.fetch_count')"
            class="mb-4"
          >
            <b-form-spinbutton
              id="input-required-pickups"
              v-model="required_pickups"
              min="0"
              max="100"
              inline
            />
          </b-form-group>

          <b-form-group
            v-if="applyDetailsVisible"
            :label="$i18n('group.application_requirements.member_since_weeks')"
            class="mb-4"
          >
            <b-form-spinbutton
              id="input-required-weeks"
              v-model="required_weeks"
              min="0"
              max="52"
              inline
            />
          </b-form-group>
        </b-form>

        <b-alert variant="info" show>
          <i class="fas fa-info-circle" />
          {{ $i18n('group.member_list.old_edit_hint') }}
          <a :href="$url('members', group.id)">{{ $i18n('group.member_list.old_edit_hint_link') }}</a>
        </b-alert>

        <div>
          <b-button
            id="submit-button"
            type="submit"
            variant="primary"
            inline
            @click="submit"
            @keydown.enter="submit"
          >
            {{ $i18n('group.actions.save') }}
          </b-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import FileUpload from '@/components/upload/FileUpload'
import { required, minLength } from 'vuelidate/lib/validators'
import i18n from '@/helper/i18n'
import { BFormSpinbutton } from 'bootstrap-vue'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import { updateGroup } from '@/api/groups'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'

export default {
  components: { MarkdownInput, FileUpload, BFormSpinbutton },
  props: {
    group: { type: Object, required: true },
  },
  data () {
    return {
      name: this.group.name,
      description: this.group.teaser,
      photo: this.group.photo,
      apply_type: this.group.apply_type,
      apply_type_options: [
        { value: 0, text: i18n('group.application_requirements.nobody') },
        { value: 1, text: i18n('group.application_requirements.requires_properties') },
        { value: 2, text: i18n('group.application_requirements.everybody') },
        { value: 3, text: i18n('group.application_requirements.open') },
      ],
      required_bananas: this.group.banana_count,
      required_pickups: this.group.fetch_count,
      required_weeks: this.group.week_num,
    }
  },
  computed: {
    validFileName () {
      return this.photo !== undefined && this.photo.startsWith('workgroup') ? '/img/' + this.photo : this.photo
    },
    applyDetailsVisible () {
      return this.apply_type === 1
    },
  },
  validations: {
    name: { required, minLength: minLength(1) },
    description: { required, minLength: minLength(1) },
  },
  methods: {
    onPhotoChange (file) {
      this.photo = file.url
    },
    async submit () {
      showLoader()
      try {
        await updateGroup(this.group.id, this.name, this.description, this.photo, this.apply_type, this.required_bananas,
          this.required_pickups, this.required_weeks)
        pulseSuccess(i18n('globals.saved'))
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

<style scoped>
</style>

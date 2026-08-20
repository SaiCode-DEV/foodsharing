<template>
  <Container :title="$t('group.edit.title', { group: group.name })" wrap-content>
    <b-form
      @submit="submit"
    >
      <b-form-group
        :label="$t('group.name')"
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
          {{ $t('group.edit.name_required') }}
        </div>
      </b-form-group>

      <b-form-group
        :label="$t('group.description')"
        class="mb-4"
      >
        <MarkdownInput
          ref="md-input"
          variant="outline-primary"
          :placeholder="$t('group.edit.description_placeholder')"
          :rows="2"
          :conceal-toolbar="true"
          :value="description"
          :region-id="group.id"
          @update:value="newValue => description = newValue"
        />
        <div
          v-if="v$.description.$error"
          class="invalid-feedback"
        >
          {{ $t('group.edit.description_required') }}
        </div>
      </b-form-group>

      <b-form-group
        :label="$t('group.photo')"
        class="mb-4"
      >
        <file-upload
          :filename="validFileName"
          :is-image="true"
          :img-width="600"
          :img-height="400"
          show-clear-button
          @change="onPhotoChange"
        />
      </b-form-group>

      <b-form-group
        class="mb-4"
      >
        <template #label>
          {{ $t('group.sorting_scheme.category') }}
          <Info info-key="group_category" />
        </template>
        <b-form-select
          id="input-group-category"
          v-model="groupCategory"
          :options="groupCategoryOptions"
        />
      </b-form-group>

      <b-form-group
        :label="$t('group.applications')"
        class="mb-4"
      >
        <b-form-select
          id="input-application-requirement"
          v-model="apply_type"
          :options="apply_type_options"
        />
      </b-form-group>

      <b-form-group
        v-if="apply_type === 2"
        :label="$t('group.application_prompt')"
        class="mb-4"
      >
        <MarkdownInput
          id="input-application-prompt"
          :value.sync="applicationPrompt"
          variant="outline-primary"
          :rows="3"
          :conceal-toolbar="true"
          :region-id="group.id"
          :placeholder="$t('group.apply.default_prompt')"
        />
      </b-form-group>
    </b-form>

    <div>
      <b-button
        id="submit-button"
        type="submit"
        variant="primary"
        inline
        @click="submit"
        @keydown.enter="submit"
      >
        {{ $t('group.actions.save') }}
      </b-button>
    </div>
  </Container>
</template>

<script>
import FileUpload from '@/components/upload/FileUpload'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength } from '@vuelidate/validators'
import i18n from '@/helper/i18n'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import { updateGroup } from '@/api/groups'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import Container from '@/components/Container/Container.vue'
import Info from '../Help/Info.vue'
import { GROUP_CATEGORY } from '@/stores/groups'

export default {
  components: { Container, MarkdownInput, FileUpload, Info },
  props: {
    group: { type: Object, required: true },
  },
  setup () {
    return {
      v$: useVuelidate(),
    }
  },
  data () {
    return {
      name: this.group.name,
      description: this.group.teaser,
      photo: this.group.photo,
      apply_type: this.group.apply_type,
      groupCategory: this.group.category_id,
      apply_type_options: [
        { value: 0, text: i18n('group.application_requirements.nobody') },
        { value: 2, text: i18n('group.application_requirements.everybody') },
        { value: 3, text: i18n('group.application_requirements.open') },
      ],
      groupCategoryOptions: Object.entries(GROUP_CATEGORY).map(([key, category]) => ({ value: category.id, text: i18n('group.category.' + key.toLowerCase()) })),
      applicationPrompt: this.group.application_prompt || '',
    }
  },
  computed: {
    validFileName () {
      return this.photo !== undefined && this.photo.startsWith('workgroup') ? '/img/' + this.photo : this.photo
    },
  },
  validations: {
    name: { required, minLength: minLength(1) },
    description: { required, minLength: minLength(1) },
  },
  methods: {
    onPhotoChange (file) {
      this.photo = file?.uuid
    },
    async submit () {
      showLoader()
      try {
        await updateGroup(this.group.id, this.name, this.description, this.photo, this.apply_type, this.applicationPrompt, this.groupCategory)
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

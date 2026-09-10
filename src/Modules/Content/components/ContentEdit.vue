<template>
  <Container
    :collapsible="false"
    :title="i18n('contentmanagement')"
  >
    <div class="list-group-item">
      <b-form class="blog-edit-form">
        <b-form-group>
          <label for="name" class="h5">{{ i18n('name') }}</label>
          <b-form-input
            id="name"
            v-model="formData.name"
            type="text"
            name="name"
            required
          />
        </b-form-group>

        <b-form-group>
          <label for="title" class="h5">{{ i18n('title') }}</label>
          <b-form-input
            id="title"
            v-model="formData.title"
            type="text"
            name="title"
            required
          />
        </b-form-group>

        <b-form-group>
          <div class="d-flex flex-wrap justify-content-between">
            <label for="content-body" class="h5">{{ i18n('content.content') }}</label>
            <div>
              <b-form-checkbox
                v-model="editRaw"
                name="editRaw"
                value="true"
                switch
              >
                {{ i18n('content.edit_raw') }}
              </b-form-checkbox>
            </div>
          </div>
          <QuillEditor
            v-if="!editRaw"
            id="content-body"
            v-model="formData.body"
          />
          <b-form-textarea
            v-else
            id="content-body"
            v-model="formData.body"
            class="ui-widget-content"
            rows="10"
          />
        </b-form-group>
      </b-form>
      <b-button variant="secondary" @click="saveContent">
        {{ i18n('button.save') }}
      </b-button>
      <b-button variant="outline-primary" @click="backToOverview">
        {{ i18n('button.cancel') }}
      </b-button>
    </div>
  </Container>
</template>

<script setup>
import { defineProps, onMounted, ref, computed } from 'vue'
import { showLoader, hideLoader, pulseSuccess, pulseError } from '@/script'
import { navigate } from '@/helper/router'
import i18n from '@/helper/i18n'
import { addContent, editContent, getContent } from '@/api/content'
import QuillEditor from '@/components/QuillEditor.vue'
import { url } from '@/helper/urls'
import Container from '@/components/Container/Container.vue'

const props = defineProps({
  contentId: {
    type: [Number, String],
    default: null,
  },
})

const isNewContent = computed(() => !props.contentId)

const editRaw = ref(false)

const formData = ref({
  name: '',
  title: '',
  body: '',
})

async function saveContent () {
  showLoader()
  try {
    if (isNewContent.value) {
      await addContent(formData.value)
      pulseSuccess(i18n('content.new_success'))
      backToOverview()
    } else {
      await editContent(props.contentId, formData.value)
      pulseSuccess(i18n('content.edit_success'))
      backToOverview()
    }
  } catch (error) {
    console.error('saveContent', error)
    pulseError(i18n('content.error'))
  } finally {
    hideLoader()
  }
}

function backToOverview () {
  navigate(url('contentEdit'))
}

onMounted(() => {
  if (isNewContent.value) {
    return
  }
  showLoader()
  getContent(props.contentId).then((response) => {
    formData.value = response
    hideLoader()
  })
})
</script>

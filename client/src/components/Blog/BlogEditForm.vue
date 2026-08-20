<template>
  <Container
    :collapsible="false"
    :title="title"
    :tooltip-key="i18n('blog.publish-info')"
  >
    <div class="list-group-item">
      <b-form class="blog-edit-form">
        <b-form-group :label="i18n('blog.title')">
          <b-form-input
            id="title"
            v-model="formData.title"
            type="text"
            name="title"
            required
          />
        </b-form-group>

        <b-form-group :label="i18n('region.type.region')">
          <b-form-select
            v-model="selectedRegionId"
            :options="regionStore.regions"
            label="regionName"
            value-field="id"
            text-field="name"
          />
        </b-form-group>

        <b-form-group :label="i18n('blog.teaser')">
          <b-form-textarea
            id="teaser"
            v-model="formData.teaser"
            type="text"
            name="teaser"
            required
            rows="3"
          />
        </b-form-group>

        <b-form-group :label="i18n('blog.content')">
          <QuillEditor
            id="blog-content"
            v-model="formData.content"
          />
        </b-form-group>
        <b-form-group :label="i18n('picture')">
          <file-upload
            class="pt-2"
            :filename="formData.picture"
            :is-image="true"
            :img-width="BLOG_POST_OPTIONS.IMAGE.WIDTH"
            :img-height="BLOG_POST_OPTIONS.IMAGE.HEIGHT"
            @change="onFileChange"
          />
        </b-form-group>
      </b-form>
      <div class="d-flex justify-content-between m-2">
        <b-button variant="outline-secondary" @click="backToOverview">
          {{ i18n('button.cancel') }}
        </b-button>
        <b-button variant="primary" @click="saveBlogPost">
          {{ i18n('button.save') }}
        </b-button>
      </div>
    </div>
  </Container>
</template>

<script setup>
import { defineProps, onMounted, computed, ref } from 'vue'
import { showLoader, hideLoader, pulseSuccess, pulseError } from '@/script'
import i18n from '@/helper/i18n'
import FileUpload from '@/components/upload/FileUpload'
import { addBlogpost, editBlogpost, getBlogpost } from '@/api/blog'
import QuillEditor from '@/components/QuillEditor.vue'
import { url } from '@/helper/urls'
import { useRegionStore } from '@/stores/regions'
import Container from '@/components/Container/Container.vue'
import { BLOG_POST_OPTIONS } from '@/consts'

const regionStore = useRegionStore()

const props = defineProps({
  blogId: {
    type: [Number, String],
    default: null,
  },
  regionId: {
    type: [Number, String],
    default: null,
  },
})

const isNewBlog = computed(() => !props.blogId)

const title = computed(() => {
  if (isNewBlog.value) {
    return i18n('blog.new')
  } else {
    return i18n('blog.edit')
  }
})

const selectedRegionId = ref(props.regionId || regionStore.regions[0]?.id)

const formData = ref({
  title: '',
  content: '',
  teaser: '',
  picture: '',
})

async function saveBlogPost () {
  showLoader()
  try {
    if (isNewBlog.value) {
      await addBlogpost(selectedRegionId.value, formData.value)
      pulseSuccess(i18n('blog.success.new'))
      backToOverview()
    } else {
      await editBlogpost(props.blogId, selectedRegionId.value, formData.value)
      pulseSuccess(i18n('blog.success.edit'))
      backToOverview()
    }
  } catch (error) {
    console.error('saveBlogPost', error)
    pulseError(i18n('blog.failure.edit'))
  } finally {
    hideLoader()
  }
}

function backToOverview () {
  window.location.href = url('blogList')
}

function onFileChange (file) {
  formData.value.picture = file.uuid
}

onMounted(async () => {
  if (isNewBlog.value) {
    return
  }
  // Load blogpost data
  showLoader()
  try {
    formData.value = await getBlogpost(props.blogId)
  } catch (error) {
    pulseError(i18n('error_unexpected'))
  } finally {
    hideLoader()
  }
})
</script>
<style scoped>
  #teaser {
    min-height: 3rem;
  }
</style>

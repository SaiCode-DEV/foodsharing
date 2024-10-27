<template>
  <div>
    <VueEditor
      :id="props.id"
      :value="value"
      :use-custom-image-handler="true"
      class="ui-widget-content"
      :editor-toolbar="customToolbar"
      @input="updateText"
      @image-added="handleImageAdded"
    />
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'
import { VueEditor, Quill } from 'vue2-editor'
import { uploadFile } from '@/api/uploads'
import { showLoader, hideLoader, pulseError } from '@/script'
import i18n from '@/helper/i18n'

const base64ImageRegex = /<img[^>]+src=["']data:image\/(png|jpg|jpeg|gif);base64,([^"']+)["'][^>]*>/i

const props = defineProps({
  id: {
    type: String,
    default: 'editor',
  },
  value: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['input'])

function updateText (value) {
  if (base64ImageRegex.test(value)) {
    pulseError(i18n('error_base64_image'))
    value = value.replace(base64ImageRegex, '')
  }
  emit('input', value)
}

const customToolbar = [
  [{ header: [false, 1, 2, 3, 4, 5, 6] }],
  ['bold', 'italic', 'underline', 'strike'], // toggled buttons
  [
    { align: '' },
    { align: 'center' },
    { align: 'right' },
    { align: 'justify' },
  ],
  ['blockquote', 'code-block'],
  [{ list: 'ordered' }, { list: 'bullet' }, { list: 'check' }],
  [{ indent: '-1' }, { indent: '+1' }], // outdent/indent
  ['link'],
  ['clean'], // remove formatting button
]

const Block = Quill.import('blots/block')
class Div extends Block {}
Div.tagName = 'div'
Div.blotName = 'div'
Div.allowedChildren = Block.allowedChildren
Div.allowedChildren.push(Block)
Quill.register(Div)

async function handleImageAdded (file, Editor, cursorLocation, resetUploader) {
  if (!file) return
  showLoader()
  try {
    const base64 = await fileToBase64(file)

    const res = await uploadFile(file.name, base64)
    Editor.insertEmbed(cursorLocation, 'image', res.url)
    resetUploader()
  } catch (err) {
    if (err.jsonContent?.message) {
      pulseError(err.jsonContent?.message)
    } else {
      console.error(err)
    }
  } finally {
    hideLoader()
  }
}

function fileToBase64 (file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.readAsDataURL(file)
    reader.onload = () => resolve(reader.result.split(',')[1]) // extract base64 string
    reader.onerror = error => reject(error)
  })
}

</script>

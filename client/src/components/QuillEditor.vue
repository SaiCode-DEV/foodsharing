<template>
  <div>
    <VueEditor
      :id="props.id"
      :value="value"
      :use-custom-image-handler="true"
      class="ui-widget-content"
      :editor-toolbar="customToolbar"
      :editor-options="editorOptions"
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
import { url } from '@/helper/urls'

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

// Quill clamps the link popup against `bounds`, which defaults to document.body.
// Without this the popup escapes the editor whenever the cursor sits near its
// left edge. The id lands on the element quill turns into its container.
const editorOptions = { bounds: `#${props.id}` }

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
const BlockEmbed = Quill.import('blots/block/embed')

class Div extends Block {}
Div.tagName = 'div'
Div.blotName = 'customDiv'
Div.allowedChildren = Block.allowedChildren
Div.allowedChildren.push(Block)
Quill.register(Div)

// Treat <details>/<summary> as an atomic block embed so Quill preserves it.
// The entire <details> element (including its <summary>) is stored as a single
// opaque blob that the editor does not try to parse internally.
class DetailsBlot extends BlockEmbed {
  static create (value) {
    const node = super.create()
    if (typeof value === 'string') {
      node.innerHTML = value
    }
    return node
  }

  static value (node) {
    return node.innerHTML
  }
}
DetailsBlot.blotName = 'details'
DetailsBlot.tagName = 'details'
Quill.register(DetailsBlot)

async function handleImageAdded (file, Editor, cursorLocation, resetUploader) {
  if (!file) return
  showLoader()
  try {
    const base64 = await fileToBase64(file)

    const res = await uploadFile(file.name, base64)
    Editor.insertEmbed(cursorLocation, 'image', url('upload', res.uuid))
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

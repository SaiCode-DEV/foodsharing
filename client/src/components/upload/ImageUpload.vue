<template>
  <div class="image-upload">
    <!-- TODO single image variant -->
    <!-- TODO allow cropping / rotating images -->
    <input
      ref="uploadElement"
      accept="image/*"
      name="imagefile[]"
      class="d-none"
      type="file"
      multiple
      @change="onFileChange"
    >
    <Gallery
      ref="gallery"
      :images="previewImgs"
      :height-in-px="galleryHeightInPx"
      allow-delete
      allow-reorder
      @delete-image="deleteImage"
    />
    <button
      v-if="uploadButton"
      class="btn btn-sm btn-primary btn-block mt-2"
      :class="{'disabledLoading': isLoading}"
      @click.prevent="openUploadDialog"
    >
      <i class="fas fa-images" />
      {{ $t('upload.images') }}
    </button>
  </div>
</template>

<script>
import { uploadFile } from '@/api/uploads'
import Gallery from '@/components/Images/Gallery'
import { pulseError } from '@/script'

export default {
  components: { Gallery },
  props: {
    uploadButton: { type: Boolean, default: true },
    galleryHeightInPx: { type: Number, default: 75 },
    previousImages: { type: Array, default: () => [] },
  },
  data () {
    return {
      isLoading: false,
      images: this.previousImages.map(url => ({
        objectUrl: url,
        file: {},
        key: url,
        uploaded: true,
      })),
    }
  },
  computed: {
    previewImgs () {
      return this.images.map(image => image.objectUrl)
    },
  },
  watch: {
    images () {
      this.$emit('change', this.images.length > 0)
    },
  },
  methods: {
    openUploadDialog () {
      if (this.isLoading) { return }
      this.$refs.uploadElement.click()
    },
    async onFileChange () {
      this.isLoading = true
      const files = this.$refs.uploadElement.files
      const invalidFiles = []
      for (const file of files) {
        const key = this.fileKey(file)
        if (!this.images.find(image => image.key === key)) {
          const objectUrl = URL.createObjectURL(file)
          const img = new Image()
          const isImage = new Promise(resolve => {
            img.addEventListener('load', () => resolve(true))
            img.addEventListener('error', () => resolve(false))
          })
          img.src = objectUrl
          if (await isImage) {
            this.images.push({ key, file, objectUrl, uploaded: false })
          } else {
            URL.revokeObjectURL(objectUrl)
            invalidFiles.push(file)
          }
        }
      }
      if (invalidFiles.length) {
        pulseError(this.$t('upload.invalid_image_files', {
          // still passed: nine other translations keep the {count} placeholder
          count: invalidFiles.length,
          fileNames: invalidFiles.map(file => file.name).join(', '),
        }))
      }
      this.isLoading = false
    },
    fileKey (file) {
      return `${file.lastModified} ${file.size} ${file.name}`
    },
    deleteImage (index) {
      const image = this.images.splice(index, 1)[0]
      URL.revokeObjectURL(image.objectUrl)
    },
    clearImages () {
      this.images.forEach(image => URL.revokeObjectURL(image.objectUrl))
      this.images = []
    },
    async uploadImages () {
      if (!this.images.length) {
        return []
      }
      const order = this.$refs.gallery.getOrder()
      const orderedImages = order.map(i => this.images[i])
      const uploads = await Promise.all(orderedImages.map(this.uploadImageIfNew))
      return uploads
    },
    async uploadImageIfNew (image) {
      if (image.uploaded) {
        return image.objectUrl
      } else {
        const upload = await this.uploadImage(image)
        return (upload && typeof upload.url === 'string') ? upload.url : null
      }
    },
    async uploadImage (image) {
      try {
        const compressed = await this.compressImage(image.objectUrl)
        const reader = new FileReader()
        const loaded = new Promise(resolve => reader.addEventListener('load', resolve))
        reader.readAsBinaryString(compressed)
        await loaded
        const base64Data = btoa(reader.result)
        return await uploadFile(image.file.name, base64Data)
      } catch (err) {
        console.error(err)
        // Without this the caller only shows a generic "could not be created" and
        // nobody can tell a too large file from a rejected type (#2804).
        pulseError(this.$t(this.uploadErrorKey(err), { fileName: image.file.name }))
      }
    },
    uploadErrorKey (err) {
      // the endpoint answers 400 for oversized files, broken images and bad
      // base64 alike, so only a real 413 from the web server means "too large"
      if (err?.code === 413) {
        return 'upload.too_large'
      }
      if (err?.code === 403 || err?.code === 415) {
        return 'upload.type_rejected'
      }
      return 'upload.failed'
    },
    async compressImage (objectUrl, type = 'image/jpeg', quality = 0.9, maxPxls = 1e6) {
      const img = new Image()
      const loaded = new Promise(resolve => img.addEventListener('load', resolve))
      img.src = objectUrl
      await loaded
      let { width, height } = img
      if (width * height > maxPxls) {
        const factor = Math.sqrt(maxPxls / (width * height))
        width = Math.round(width * factor)
        height = Math.round(height * factor)
      }
      const canvas = document.createElement('canvas')
      const ctx = canvas.getContext('2d')
      canvas.width = width
      canvas.height = height
      ctx.drawImage(img, 0, 0, img.width, img.height, 0, 0, width, height)
      return await new Promise(resolve => canvas.toBlob(resolve, type, quality))
    },
    setImages (images) {
      this.clearImages()
      this.images = images.map(url => ({
        objectUrl: url,
        file: {},
        key: url,
        uploaded: true,
      }))
    },
  },
}
</script>

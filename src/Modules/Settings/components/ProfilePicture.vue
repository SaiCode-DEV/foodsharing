<template>
  <b-modal
    ref="profile_picture_modal"
    title="Profilfoto"
    ok-only
    modal-class="bootstrap"
    header-class="d-flex"
    content-class="pr-3 pt-3"
  >
    <file-upload
      class="pt-2"
      :filename="value"
      :is-image="true"
      :img-width="imgHeight"
      :img-height="imgWidth"
      @change="onFileChange"
    />
  </b-modal>
</template>

<script>
import FileUpload from '@/components/upload/FileUpload'
import { setProfilePhoto } from '@/api/settings'
import { hideLoader, pulseError, showLoader } from '@/script'
import i18n from '@/helper/i18n'

export default {
  components: { FileUpload },
  props: {
    initialValue: {
      type: String,
      default: null,
    },
    imgHeight: {
      type: Number,
      default: 0,
    },
    imgWidth: {
      type: Number,
      default: 0,
    },
  },
  data () {
    return {
      value: this.initialValue,
    }
  },
  methods: {
    onFileChange (file) {
      // console.log(file)
      this.value = file.url

      showLoader()
      try {
        setProfilePhoto(file.uuid)
      } catch (e) {
        console.error(e)
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
    show () {
      this.$refs.profile_picture_modal.show()
    },
  },
}
</script>

<style lang="scss">

</style>

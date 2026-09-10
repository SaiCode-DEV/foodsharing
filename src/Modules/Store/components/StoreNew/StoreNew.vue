<template>
  <Container :title="title">
    <NameAndRegion
      v-if="page === NEW_STORE_PAGE.NAME_AND_REGION"
      :chosen-region="chosenRegion"
      :name.sync="name"
      :region.sync="region"
      @next="next()"
    />
    <WallAndPublicInfo
      v-if="page === NEW_STORE_PAGE.WALL_AND_PUBLIC_INFO"
      :public-info.sync="publicInfo"
      :first-post.sync="firstPost"
      @prev="prev()"
      @next="next()"
    />
    <LocationAndCreate
      v-if="page === NEW_STORE_PAGE.LOCATION_AND_CREATE"
      :address.sync="address"
      :location.sync="location"
      @prev="prev()"
      @submit="submit()"
    />
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import NameAndRegion from './NameAndRegion.vue'
import WallAndPublicInfo from './WallAndPublicInfo.vue'
import LocationAndCreate from './LocationAndCreate.vue'
import { pulseError, pulseSuccess } from '@/script'
import { navigate } from '@/helper/router'
import i18n from '@/helper/i18n'
import { addStore } from '@/api/stores'

const NEW_STORE_PAGE = Object.freeze({
  NAME_AND_REGION: 1,
  WALL_AND_PUBLIC_INFO: 2,
  LOCATION_AND_CREATE: 3,
})

const pageLength = Object.keys(NEW_STORE_PAGE).length

export default {
  components: { NameAndRegion, Container, WallAndPublicInfo, LocationAndCreate },
  props: { chosenRegion: { type: Object, required: true } },
  data () {
    return {
      page: 1,
      editMode: true,
      name: null,
      region: null,
      publicInfo: null,
      firstPost: null,
      location: null,
      address: null,
    }
  },
  computed: {
    NEW_STORE_PAGE () {
      return NEW_STORE_PAGE
    },
    title () {
      return i18n('storeedit.add-new') + ' ( ' + this.page + ' / ' + pageLength + ')'
    },
  },
  methods: {
    prev () {
      this.page--
    },
    next () {
      this.page++
    },
    async submit () {
      this.isLoading = true
      try {
        const store = {
          name: this.name,
          location: this.location,
          street: this.address.street,
          zipCode: this.address.zipCode,
          city: this.address.city,
          publicInfo: this.publicInfo,
        }
        const response = await addStore(this.region.id, store, this.firstPost)
        pulseSuccess(i18n('storeedit.add_success'))
        navigate(this.$url('store', response.id))
      } catch (err) {
        pulseError(`${i18n('error_unexpected')}<br><br> ${err.message}`)
      }
      this.isLoading = false
    },
  },
}
</script>

<style scoped lang="scss">

</style>

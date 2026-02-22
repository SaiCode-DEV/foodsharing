<template>
  <BasePage v-if="loaded">
    <!-- TODO add breadcrumbs -->
    <template #top>
      <b-alert
        :show="!isOpen"
        variant="danger"
        class="text-center"
      >
        <i class="fas fa-eye-slash mr-2" />
        {{ $t('fsp.onlySuggested') }}
      </b-alert>
    </template>
    <template #left>
      <Container v-if="permissions.isLoggedIn" :title="$t('options')">
        <ContainerButton
          v-if="!permissions.isFollower && isOpen"
          variant="success"
          text-key="fsp.follow"
          icon="fas fa-bell"
          :disabled="operationLoading"
          @click="$refs.followModal.show()"
        />
        <ContainerButton
          v-if="permissions.isFollower"
          variant="warning"
          text-key="fsp.unfollow"
          icon="fas fa-bell-slash"
          :disabled="operationLoading"
          @click="unfollow"
        />
        <ContainerButton
          v-if="permissions.mayDelete && !isOpen"
          variant="success"
          text-key="fsp.accept"
          icon="fas fa-check"
          :disabled="operationLoading"
          @click="accept"
        />
        <ContainerButton
          v-if="permissions.mayEdit"
          variant="warning"
          text-key="fsp.edit"
          icon="fas fa-pen"
          :href="$url('foodsharepointEdit', id)"
        />
        <ContainerButton
          v-if="permissions.mayDelete"
          variant="danger"
          text-key="fsp.delete"
          icon="fas fa-trash"
          :disabled="operationLoading"
          @click="remove"
        />
      </Container>
      <Container
        v-if="fsp.managers?.length"
        :title="$t('fsp.managers')"
        wrap-content="p-0"
      >
        <AvatarList :profiles="fsp.managers" :max-visible-avatars="10" />
      </Container>
      <Container :title="$t('fsp.address')">
        <div class="list-group-item d-flex justify-content-between">
          <div>
            {{ fsp.address.street }} <br>
            {{ fsp.address.postalCode }} {{ fsp.address.city }} <br><br>
            <b>{{ $t('bezirk') }}:</b> <a :href="$url('publicRegion', fsp.regionId)" v-text="fsp.regionName" /><br><br>
            <a :href="$url('map', { foodSharePointId: id })">
              <i class="fas fa-map-marker-alt" />
              {{ $t('fsp.show_on_large_map') }}
            </a>
          </div>
          <NavigateWithSelector
            :latitude="fsp.location.lat"
            :longitude="fsp.location.lon"
            vertical
          />
        </div>

        <LeafletLocationPicker
          class="list-group-item p-0 fsp-minimap"
          :icon="icon"
          :coordinates="fsp.location"
          :zoom="15"
        />
      </Container>
    </template>
    <Container :title="fsp.name">
      <img class="fsp-head" :style="{ backgroundImage: `url(${fsp.picture || '/img/foodSharePointHead.jpg'})`}">
      <div class="list-group-item">
        <Markdown :source="fsp.description" />
      </div>
      <div class="list-group-item fsp-meta-data">
        <span>
          <span v-text="$t('fsp.createdAt')" />
          <Time
            :time="fsp.createdAt"
            :muted="false"
            normal-size
          />
        </span>
        <span v-text="$t('fsp.followerCount', fsp)" />
      </div>
    </Container>

    <b-alert :show="permissions.isLoggedIn" variant="info">
      <i class="fas fa-info-circle mr-2" />
      {{ $t('fsp.publicwall') }}
    </b-alert>

    <Wall
      target="fairteiler"
      :target-id="id"
      :page-size="5"
    />
    <b-modal
      ref="followModal"
      :title="$t('fsp.follow')"
      centered
      :ok-disabled="sendMail === null"
      :ok-title="$t('button.save')"
      :cancel-title="$t('button.cancel')"
      @ok="follow"
    >
      <p v-text="$t('fsp.info.descModal')" />
      <b-form-radio-group v-model="sendMail" :options="notificationOptions" />
    </b-modal>
  </BasePage>
</template>
<script>
import { getFoodSharePoint, getFoodSharePointPermissions, followFoodSharePoint, unfollowFoodSharePoint, deleteFoodSharePoint, acceptFoodSharePoint } from '@/api/foodsharepoints'
import BasePage from '@/views/pages/Layout/BasePage.vue'
import Container from '@/components/Container/Container.vue'
import AvatarList from '@/components/Avatar/AvatarList.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import LeafletLocationPicker from '@/components/map/LeafletLocationPicker'
import Wall from '@/components/Wall/Wall.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import Time from '@/components/Time.vue'
import { useUserStore } from '@/stores/user'
import L from 'leaflet'
import NavigateWithSelector from '@/components/UI/NavigateWithSelector.vue'
L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

export default {
  components: {
    BasePage,
    Container,
    AvatarList,
    Markdown,
    LeafletLocationPicker,
    Wall,
    ContainerButton,
    Time,
    NavigateWithSelector,
  },
  props: {
    id: { type: Number, required: true },
  },
  data () {
    return {
      fsp: null,
      permissions: null,
      loaded: false,
      operationLoading: false,
      icon: L.AwesomeMarkers.icon({ icon: 'recycle', markerColor: 'beige' }),
      sendMail: null,
      notificationOptions: [
        { text: this.$t('fsp.info.bell'), value: false },
        { text: this.$t('fsp.info.mail'), value: true },
      ],
    }
  },
  computed: {
    isOpen () { return this.fsp.status === 1 },
  },
  async mounted () {
    await Promise.all([
      getFoodSharePoint(this.id).then((result) => { this.fsp = result }),
      getFoodSharePointPermissions(this.id).then((result) => { this.permissions = result }),
    ])
    this.permissions.isLoggedIn = useUserStore().isLoggedIn
    this.loaded = true
  },
  methods: {
    async remove () {
      if (!await this.$confirmationDialogue('fsp.deleteConfirm')) return
      this.operationLoading = true
      await deleteFoodSharePoint(this.id)
      location.href = this.$url('dashboard')
    },
    async follow () {
      this.operationLoading = true
      await followFoodSharePoint(this.id, this.sendMail)
      this.permissions.isFollower = true
      this.fsp.followerCount++
      this.operationLoading = false
    },
    async unfollow () {
      this.operationLoading = true
      await unfollowFoodSharePoint(this.id)
      this.permissions.isFollower = false
      this.fsp.followerCount--
      this.operationLoading = false
    },
    async accept () {
      this.operationLoading = true
      await acceptFoodSharePoint(this.id)
      this.fsp.status = 1
      this.operationLoading = false
    },
  },
}
</script>
<style lang="scss" scoped>
.fsp-head {
  height: 150px;
  width: 100%;
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
}

.fsp-minimap {
  border-color: var(--fs-border-default);
}

.fsp-meta-data {
  display: flex;
  justify-content: space-between;
  gap: 2em;
  font-size: smaller;
}
</style>

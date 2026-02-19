<template>
  <BasePage>
    <template #top>
      <Breadcrumbs :items="breadcrumbs" />
      <b-alert :show="!userStore.isLoggedIn" variant="info">
        <markdown :source="$t('basket.login')" />
      </b-alert>
    </template>

    <template v-if="userStore.isLoggedIn" #right>
      <!-- Information about the owner of the basket; forms for requesting and editing-->
      <Container hide-header wrap-content>
        <h3>{{ $t('basket.provider') }}</h3>
        <AvatarList
          class="mb-2"
          :profiles="[basket.creator]"
          :max-visible-avatars="1"
        />
        <request-form
          v-if="props.mayRequest"
          :basket-id="props.basket.id"
          :basket-creator-id="props.basket.creator.id"
          :initial-has-requested="props.hasRequested"
          :initial-request-count="props.basket.requestCount"
          :mobile-number="mobileNumber"
          :landline-number="landlineNumber"
          :allow-request-by-message="allowContactByMessage"
        />
        <edit-form
          v-if="mayDelete"
          :basket="basket"
          :may-edit="mayEdit"
        />
      </Container>

      <!-- List of users who requested the basket -->
      <Container
        v-if="requests && basket.creator.id === userStore.getUserId"
        hide-header
        wrap-content
      >
        <h3>{{ $t('basket.requests', { count: requests.length }) }}</h3>
        <ul class="linklist request-list">
          <li v-for="request in requests" :key="request.fs_id">
            <a href="#" @click="openChat(request.fs_id)">
              <span class="name">{{ request.fs_name }}</span>
              <Avatar :image="request.fs_photo" :size="50" />
              <span class="time"> {{ $dateFormatter.dateTime(new Date(request.requestedAt)) }}</span>
            </a>
          </li>
        </ul>
      </Container>

      <!-- Map of the basket's location -->
      <Container hide-header>
        <basket-location-map
          :zoom="MAP_CONSTANTS.ZOOM_CITY"
          :coordinates="basket.location"
        />
      </Container>
    </template>

    <!-- Basket's description and photos -->
    <basket-container :basket="basket" />
  </BasePage>
</template>
<script setup>
import { computed, defineProps } from 'vue'
import { useUserStore } from '@/stores/user.js'
import conversationStore from '@/stores/conversations'
import { MAP_CONSTANTS } from '@/stores/map'
import { BASKET_CONTACT_TYPE } from '@/stores/baskets'
import { url } from '@/helper/urls'
import i18n from '@/helper/i18n'
import BasketContainer from '@/components/Basket/BasketContainer.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import Container from '@/components/Container/Container.vue'
import BasketLocationMap from '@/components/Basket/BasketLocationMap.vue'
import Avatar from '@/components/Avatar/Avatar.vue'
import BasePage from '@/views/pages/Layout/BasePage.vue'
import RequestForm from '@/components/Basket/RequestForm.vue'
import EditForm from '@/components/Basket/EditForm.vue'
import AvatarList from '@/components/Avatar/AvatarList.vue'
import Breadcrumbs from '@/views/partials/Navigation/Breadcrumbs.vue'

const props = defineProps({
  basket: { type: Object, required: true },
  /**
   * Contains all requests to the basket by other users, or null if the user does not own this basket.
   */
  requests: { type: Array, default: null },
  /**
   * If this basket is owned by someone else and the user has already sent a request.
   */
  hasRequested: { type: Boolean, default: false },
  mayRequest: { type: Boolean, default: false },
  mayEdit: { type: Boolean, default: false },
  mayDelete: { type: Boolean, default: false },
})

const userStore = useUserStore()

const allowContactByMessage = computed(() => props.basket.contactTypes.includes(BASKET_CONTACT_TYPE.BY_MESSAGE))
const allowContactByPhone = computed(() => props.basket.contactTypes.includes(BASKET_CONTACT_TYPE.BY_PHONE))
const mobileNumber = computed(() => allowContactByPhone.value && props.basket.mobile !== null ? props.basket.mobile : null)
const landlineNumber = computed(() => allowContactByPhone.value && props.basket.telephone !== null ? props.basket.telephone : null)
const breadcrumbs = computed(() => [
  { href: url('baskets'), text: i18n('terminology.baskets') },
])

function openChat (userId) {
  conversationStore.openChatWithUser(userId)
}
</script>

<style scoped lang="scss">
.request-list {
  .name {
    font-weight: bold;
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
  }

  .time {
    font-size: 10px;
    opacity: 0.8;
    margin-top: 3px;
  }
}
h3 {
  font-size: 16px;
  margin-bottom: 10px;
}
</style>

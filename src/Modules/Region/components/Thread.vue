<template>
  <div
    :class="{disabledLoading: isLoading}"
    class="bootstrap"
  >
    <div class="rounded text-white bg-primary p-2">
      <h4 :class="{'text-truncate': title.length > 150}">
        <b-skeleton v-if="isLoading" />
        <i
          v-if="stickiness < 0"
          class="fas fa-sign-in-alt fa-rotate-90 mr-1"
          :title="$t('forum.thread.bottom')"
        />
        <i
          v-if="!isOpen"
          class="fas fa-lock mr-1"
          :title="$t('forum.thread.closed')"
        />
        <i
          v-if="stickiness > 0"
          class="fas fa-thumbtack mr-1"
          :title="$t('forum.thread.sticky')"
        />
        {{ title }}

        <OverflowMenu
          variant="light"
          :options="overflowMenuOptions"
        />
      </h4>
    </div>

    <SubscribeButton
      v-if="!isLoading"
      :is-following-bell="isFollowingBell"
      :is-following-email="isFollowingEmail"
      :thread-id="id"
      @update:bell="newState => isFollowingBell = newState"
      @update:email="newState => isFollowingEmail = newState"
    />
    <HiddenPostsAlert v-if="posts.length > 3 && hasHiddenPosts" :show-hidden-posts.sync="showHiddenPosts" />

    <b-card v-if="isLoading">
      <b-skeleton width="85%" />
      <b-skeleton width="55%" />
      <b-skeleton width="70%" />
    </b-card>

    <div
      v-if="!isActive && mayModerate"
      class="card-body mb-2"
    >
      <div
        class="alert alert-warning mb-2"
        role="alert"
      >
        <span>
          {{ $t('forum.thread.inactive') }}
        </span>
      </div>
      <div>
        <button
          class="btn btn-primary btn-sm"
          @click="activateThread"
        >
          <i class="fas fa-check" /> {{ $t('forum.thread.activate') }}
        </button>
        <button
          class="btn btn-danger btn-sm float-right"
          @click="$refs.deleteModal.show()"
        >
          <i class="fas fa-trash-alt" /> {{ $t('forum.thread.delete') }}
        </button>
      </div>
    </div>
    <b-alert :show="!shownPosts.length && !isLoading" variant="info">
      <i class="fas fa-info-circle" />
      {{ $t('forum.thread.all_hidden') }}
    </b-alert>
    <div id="posts-wrapper">
      <div v-for="post in shownPosts" :key="post.id">
        <ThreadPost
          :post="post"
          :user-id="userId"
          :deep-link="getPostLink(post.id)"
          :may-hide="mayHidePosts"
          :may-moderate="mayModerate"
          :is-loading="loadingPosts.indexOf(post.id) != -1"
          :created-at="new Date(post.createdAt)"
          :may-reply="isOpen"
          :is-linked="linkedPost == post.id"
          @delete="deletePost(post)"
          @hide="hidePost(post.id, $event)"
          @reaction-add="key => addReaction(post.id, key)"
          @reaction-remove="key => removeReaction(post.id, key)"
          @reply="reply(post, true)"
          @reply-full="reply(post, false)"
          @restore="restorePost(post.id)"
        />
      </div>
    </div>

    <div
      v-if="!isLoading && !errorMessage && !posts.length"
      class="alert alert-warning"
      role="alert"
    >
      {{ $t('forum.no_posts') }}
    </div>
    <div
      v-if="errorMessage"
      class="alert alert-danger"
      role="alert"
    >
      <strong>{{ $t('error_unexpected') }}:</strong> {{ errorMessage }}
    </div>

    <HiddenPostsAlert v-if="hasHiddenPosts" :show-hidden-posts.sync="showHiddenPosts" />
    <SubscribeButton
      v-if="!isLoading"
      :is-following-bell="isFollowingBell"
      :is-following-email="isFollowingEmail"
      :thread-id="id"
      @update:bell="newState => isFollowingBell = newState"
      @update:email="newState => isFollowingEmail = newState"
    />

    <ThreadForm
      v-if="isOpen || mayModerate"
      ref="form"
      :thread-id="id"
      :is-open="isOpen"
      :region-id="regionId"
      @submit="createPost"
    />

    <b-modal
      ref="deleteModal"
      :title="$t('forum.thread.delete')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.yes_i_am_sure')"
      cancel-variant="primary"
      ok-variant="outline-danger"
      @ok="deleteThread"
    >
      {{ $t('really_delete') }}
    </b-modal>

    <b-modal
      ref="title_edit_modal"
      centered
      :title="$t('thread.rename.edit_description')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.save')"
      @ok="updateTitle"
    >
      <p>
        {{ $t('thread.rename.description_modal_text') }}
      </p>
      <b-form-input
        v-model="newTitle"
        :placeholder="$t('thread.rename.placeholder')"
        :maxlength="260"
      />
      <small v-if="newTitle?.length === 260">
        <i class="fas fa-info-circle" />
        {{ $t('thread.rename.max_length_info') }}
      </small>
    </b-modal>

    <b-modal
      ref="priorityEditModal"
      centered
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.save')"
      @ok="updateStickiness(newPriority)"
    >
      <template #modal-title>
        {{ $t('thread.priorityModal.title') }}
        <Info info-key="threadPriority" />
      </template>
      <p>
        {{ $t('thread.priorityModal.text') }}
        {{ newPriority }}
        ({{ newPriorityText }})
      </p>
      <VueSlider
        v-model="newPriority"
        :min="-1"
        :max="10"
        tooltip="none"
      />
    </b-modal>

    <JumpScrollButton
      element-id="posts-wrapper"
    />
  </div>
</template>

<script>

import * as api from '@/api/forum'
import { GET } from '@/browser'
import { HTTP_RESPONSE } from '@/consts'
import OverflowMenu from '@/components/OverflowMenu.vue'
import { pulseError, pulseWarning } from '@/script'
import { useUserStore } from '@/stores/user'
import JumpScrollButton from '@/components/JumpScrollButton.vue'
import SubscribeButton from './SubscribeButton.vue'
import ThreadForm from './ThreadForm'
import ThreadPost from './ThreadPost'
import ThreadStatus from './ThreadStatus'
import HiddenPostsAlert from './HiddenPostsAlert'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'
import Info from '@/components/Help/Info.vue'

const userStore = useUserStore()

export default {
  components: { ThreadForm, ThreadPost, OverflowMenu, JumpScrollButton, SubscribeButton, HiddenPostsAlert, VueSlider, Info },
  props: {
    id: {
      type: Number,
      default: null,
    },
  },
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      title: '',
      regionId: null,
      regionSubId: null,
      posts: [],
      creator: null,

      stickiness: 0,
      isActive: true,
      mayModerate: false,
      mayDelete: false,
      mayHidePosts: false,
      isFollowingEmail: false,
      isFollowingBell: false,
      isOnlyPostsVisible: false,

      isLoading: false,
      loadingPosts: [],
      errorMessage: null,
      newTitle: '',
      newPriority: 0,
      linkedPost: null,
      showHiddenPosts: false,

      status: ThreadStatus.THREAD_OPEN,
    }
  },
  computed: {
    userId () {
      return userStore.getUserId
    },
    userFirstName () {
      return userStore.getUserFirstName
    },
    isOpen () {
      return this.status === ThreadStatus.THREAD_OPEN
    },
    mayRename () {
      return this.mayModerate || this.userId === this.creator?.id
    },
    overflowMenuOptions () {
      return [
        { hide: !this.mayRename, icon: 'pen', textKey: 'thread.options.rename', callback: this.openEditTitleModal },
        { hide: !this.mayModerate, icon: `lock${this.isOpen ? '' : '-open'}`, textKey: `thread.options.${this.isOpen ? '' : 'un'}lock`, callback: this.updateClosed },
        { hide: !this.mayModerate || this.stickiness < 0, icon: 'thumbtack', textKey: `thread.options.${this.stickiness ? 'un' : ''}pin`, callback: () => this.updateStickiness(+(!this.stickiness)) },
        { hide: !this.mayModerate, icon: 'sort-amount-down', textKey: 'thread.options.priority', callback: this.updatePriority },
      ]
    },
    hasHiddenPosts () {
      return this.posts.some(post => post.hidden)
    },
    newPriorityText () {
      return this.$t('thread.priorityModal.' + ['lower', 'normal', 'higher'][Math.sign(this.newPriority) + 1])
    },
    shownPosts () {
      return this.showHiddenPosts ? this.posts : this.posts.filter(post => !post.hidden)
    },
  },
  async created () {
    this.isLoading = true
    await this.reload()
    await new Promise(resolve => window.setTimeout(resolve, 200))
    const pid = parseInt(GET('pid'), 10)
    if (!Number.isNaN(pid)) {
      this.linkedPost = pid
      this.scrollToPost(this.posts.find(post => post.id >= this.linkedPost), this.linkedPost)
    }
  },
  methods: {
    getPostLink (postId) {
      return this.$url('forum', this.regionId, this.regionSubId, this.id, postId)
    },
    async scrollToPost (post, linkedPostId) {
      if (!post) return
      if (post.hidden) {
        this.showHiddenPosts = true
        await this.$nextTick()
      }
      const p = window.document.querySelector(`#post-${post.id} .card-header`)
      if (p) {
        p.scrollIntoView({ behavior: 'smooth', block: 'center' })
        await new Promise(resolve => window.setTimeout(resolve, 500))
        p.parentElement.animate({
          backgroundColor: ['transparent', 'var(--fs-color-warning-alpha-60)', 'transparent'],
          offset: [0, 0.05, 1],
          easing: ['ease-out', 'ease-in'],
        }, {
          direction: 'alternate',
          duration: 4000,
          iterations: 1,
        })
      }
      if (linkedPostId && post.id !== linkedPostId) {
        pulseWarning(this.$t('forum.thread.post_not_found'))
      }
    },
    reply (post, truncate) {
      const maxQuotedLines = 5
      let quoteLines = post.body.split('\n').map(line => `> ${line}`)
      if (quoteLines.length > maxQuotedLines && truncate) {
        quoteLines = quoteLines.slice(0, maxQuotedLines)
        quoteLines[maxQuotedLines - 1] += ' [...]'
      }
      quoteLines.unshift('>')
      const intro = `> **@${post.author.id} ` + this.$t('thread.post.quote_post_wrote') + ' [' + new Date(post.createdAt).toLocaleString() + '](' + this.getPostLink(post.id) + '):**'
      quoteLines.unshift(intro)
      this.$refs.form.prepend(quoteLines.join('\n') + '\n\n')
      this.$refs.form.focus()
    },
    async reload (isDeleteAction = false) {
      try {
        const res = (await api.getThread(this.id)).data
        Object.assign(this, {
          title: res.title,
          regionId: res.regionId,
          regionSubId: res.regionSubId,
          posts: res.posts,
          stickiness: res.stickiness,
          isActive: res.isActive,
          mayModerate: res.mayModerate,
          mayDelete: res.mayDelete,
          mayHidePosts: res.mayHidePosts,
          isFollowingEmail: res.isFollowingEmail,
          isFollowingBell: res.isFollowingBell,
          status: res.status,
          creator: res.creator,
        })
        this.isLoading = false
      } catch (err) {
        if (!isDeleteAction) {
          this.isLoading = false
          this.errorMessage = err.message
        } else {
          // In this case the last post was deleted.
          window.location = this.$url('forum', this.regionId)
        }
      }
    },
    async updateStickiness (targetState) {
      try {
        await api.setStickinessThread(this.id, targetState)
        this.stickiness = targetState
      } catch (err) {
        pulseError(this.$t('error_unexpected'))
      }
    },
    async deletePost (post) {
      this.loadingPosts.push(post.id)

      try {
        await api.deletePost(post.id)
        await this.reload(true)
      } catch (err) {
        pulseError(this.$t('error_unexpected'))
      } finally {
        this.loadingPosts.splice(this.loadingPosts.indexOf(post.id), 1)
      }
    },
    async hidePost (postId, reason) {
      try {
        await api.hidePost(postId, reason)
        const post = this.posts.find(post => post.id === postId)
        if (post) {
          post.hidden = {
            reason,
            moderator: {
              name: userStore.getUserFirstName,
              id: userStore.getUserId,
            },
            time: new Date(),
          }
        }
      } catch (err) {
        pulseError(this.$t('error_unexpected'))
      }
    },
    async addReaction (postId, key) {
      try {
        await api.addReaction(postId, key)
      } catch (error) {
        pulseError(this.$t('error_unexpected'))
        console.error(error)
      }
    },
    async removeReaction (postId, key) {
      try {
        await api.removeReaction(postId, key)
      } catch (error) {
        pulseError(this.$t('error_unexpected'))
        console.error(error)
      }
    },
    async createPost (body) {
      this.errorMessage = null
      const dummyPost = {
        id: -1,
        createdAt: new Date(),
        body: body,
        reactions: {},
        author: {
          name: `${this.userFirstName} ${userStore.getUserLastName}`,
          avatar: userStore.getAvatar,
        },
      }
      this.loadingPosts.push(-1)
      this.posts.push(dummyPost)

      try {
        await api.createPost(this.id, body)
        await api.followThreadByBell(this.id)
        await this.reload()
      } catch (err) {
        if (err?.code === HTTP_RESPONSE.CONFLICT) {
          // Post already exists, refresh to show it
          window.location = this.$url('forum', this.regionId, this.regionSubId, this.id)
          return
        }

        const index = this.posts.indexOf(dummyPost)
        this.posts.splice(index, 1)

        this.errorMessage = err.message
        this.$refs.form.text = body
      }
    },

    async activateThread () {
      this.isActive = true
      try {
        await api.activateThread(this.id)
      } catch (err) {
        this.isActive = false
        pulseError(this.$t('error_unexpected'))
      }
    },
    async deleteThread () {
      this.isLoading = true
      try {
        await api.deleteThread(this.id)

        // redirect to forum overview
        window.location = this.$url('forum', this.regionId, this.regionSubId)
      } catch (err) {
        this.isLoading = false
        pulseError(this.$t('error_unexpected'))
      }
    },
    async updateClosed () {
      this.isLoading = true
      const targetStatus = [ThreadStatus.THREAD_CLOSED, ThreadStatus.THREAD_OPEN][this.status]
      try {
        await api.setThreadStatus(this.id, targetStatus)
        this.status = targetStatus
      } catch (err) {
        pulseError(this.$t('error_unexpected'))
      }
      this.isLoading = false
    },
    openEditTitleModal () {
      this.newTitle = this.title
      this.$refs.title_edit_modal.show()
    },
    async updateTitle () {
      this.isLoading = true
      try {
        await api.setTitle(this.id, this.newTitle)
        this.title = this.newTitle
      } catch (err) {
        pulseError(this.$t('error_unexpected'))
      }
      this.isLoading = false
    },
    updatePriority () {
      this.newPriority = this.stickiness
      this.$refs.priorityEditModal.show()
    },
    async restorePost (postId) {
      try {
        await api.restorePost(postId)
        const post = this.posts.find(post => post.id === postId)
        if (post) post.hidden = false
      } catch (err) {
        pulseError(this.$t('error_unexpected'))
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.card-body > .alert {
  margin-bottom: 0;
}

.text-strike {
  text-decoration: line-through;
}
</style>

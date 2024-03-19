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
          :title="$i18n('forum.thread.bottom')"
        />
        <i
          v-if="!isOpen"
          class="fas fa-lock mr-1"
          :title="$i18n('forum.thread.closed')"
        />
        <i
          v-if="stickiness > 0"
          class="fas fa-thumbtack mr-1"
          :title="$i18n('forum.thread.sticky')"
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
          {{ $i18n('forum.thread.inactive') }}
        </span>
      </div>
      <div>
        <button
          class="btn btn-primary btn-sm"
          @click="activateThread"
        >
          <i class="fas fa-check" /> {{ $i18n('forum.thread.activate') }}
        </button>
        <button
          class="btn btn-danger btn-sm float-right"
          @click="$refs.deleteModal.show()"
        >
          <i class="fas fa-trash-alt" /> {{ $i18n('forum.thread.delete') }}
        </button>
      </div>
    </div>
    <div id="posts-wrapper">
      <div v-for="post in posts" :key="post.id">
        <ThreadPost
          :id="post.id"
          :user-id="userId"
          :author="post.author"
          :body="post.body"
          :deep-link="getPostLink(post.id)"
          :reactions="post.reactions"
          :may-delete="post.mayDelete"
          :may-edit="false"
          :is-loading="loadingPosts.indexOf(post.id) != -1"
          :created-at="new Date(post.createdAt)"
          :may-reply="isOpen"
          @delete="deletePost(post)"
          @reaction-add="reactionAdd(post, arguments[0])"
          @reaction-remove="reactionRemove(post, arguments[0])"
          @reply="reply(post)"
          @scroll="scrollToPost(post.id)"
        />
      </div>
    </div>

    <div
      v-if="!isLoading && !errorMessage && !posts.length"
      class="alert alert-warning"
      role="alert"
    >
      {{ $i18n('forum.no_posts') }}
    </div>
    <div
      v-if="errorMessage"
      class="alert alert-danger"
      role="alert"
    >
      <strong>{{ $i18n('error_unexpected') }}:</strong> {{ errorMessage }}
    </div>

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
      :is-open="isOpen"
      @submit="createPost"
    />

    <b-modal
      ref="deleteModal"
      :title="$i18n('forum.thread.delete')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      cancel-variant="primary"
      ok-variant="outline-danger"
      @ok="deleteThread"
    >
      {{ $i18n('really_delete') }}
    </b-modal>

    <b-modal
      ref="title_edit_modal"
      centered
      :title="$i18n('thread.rename.edit_description')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.save')"
      @ok="updateTitle"
    >
      <p>
        {{ $i18n('thread.rename.description_modal_text') }}
      </p>
      <b-form-input
        v-model="newTitle"
        :placeholder="$i18n('thread.rename.placeholder')"
        :maxlength="260"
      />
      <small v-if="newTitle?.length === 260">
        <i class="fas fa-info-circle" />
        {{ $i18n('thread.rename.max_length_info') }}
      </small>
    </b-modal>

    <b-modal
      ref="priorityEditModal"
      centered
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.save')"
      @ok="updateStickiness(newPriority)"
    >
      <template #modal-title>
        {{ $i18n('thread.priorityModal.title') }}
        <Info info-key="threadPriority" />
      </template>
      <p>
        {{ $i18n('thread.priorityModal.text') }}
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
import OverflowMenu from '@/components/OverflowMenu.vue'
import { pulseError } from '@/script'
import DataUser from '@/stores/user'
import JumpScrollButton from './JumpScrollButton'
import SubscribeButton from './SubscribeButton.vue'
import ThreadForm from './ThreadForm'
import ThreadPost from './ThreadPost'
import ThreadStatus from './ThreadStatus'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'
import Info from '@/components/Help/Info.vue'

export default {
  components: { ThreadForm, ThreadPost, OverflowMenu, JumpScrollButton, SubscribeButton, VueSlider, Info },
  props: {
    id: {
      type: Number,
      default: null,
    },
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
      isFollowingEmail: false,
      isFollowingBell: false,
      isOnlyPostsVisible: false,

      isLoading: false,
      loadingPosts: [],
      errorMessage: null,
      newTitle: '',
      newPriority: 0,

      status: ThreadStatus.THREAD_OPEN,
    }
  },
  computed: {
    userId () {
      return DataUser.getters.getUserId()
    },
    userFirstName () {
      return DataUser.getters.getUserFirstName()
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
    newPriorityText () {
      return this.$i18n('thread.priorityModal.' + ['lower', 'normal', 'higher'][Math.sign(this.newPriority) + 1])
    },
  },
  async created () {
    this.isLoading = true
    await this.reload()
    setTimeout(() => { this.scrollToPost(GET('pid')) }, 200)
  },
  methods: {
    getPostLink (postId) {
      return this.$url('forum', this.regionId, this.regionSubId, this.id, postId)
    },
    scrollToPost (postId) {
      const p = window.document.getElementById(`post-${postId}`)
      if (p) {
        p.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
    },
    reply (post) {
      const maxQuotedLines = 5
      let quoteLines = post.body.split('\n').map(line => `> ${line}`)
      if (quoteLines.length > maxQuotedLines) {
        quoteLines = quoteLines.slice(0, maxQuotedLines)
        quoteLines[maxQuotedLines - 1] += ' [...]'
      }
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
        pulseError(this.$i18n('error_unexpected'))
      }
    },
    async deletePost (post) {
      this.loadingPosts.push(post.id)

      try {
        await api.deletePost(post.id)
        await this.reload(true)
      } catch (err) {
        pulseError(this.$i18n('error_unexpected'))
      } finally {
        this.loadingPosts.splice(this.loadingPosts.indexOf(post.id), 1)
      }
    },

    async reactionAdd (post, key, onlyLocally = false) {
      if (post.reactions[key]) {
        // reaction alrready in list, increase count by 1
        if (post.reactions[key].find(r => r.id === this.userId)) return // already given - abort
        post.reactions[key].push({ id: this.userId, name: this.userName })
      } else {
        // reaction not in the list yet, append it
        this.$set(post.reactions, key, [{ id: this.userId, name: this.userName }])
      }

      if (!onlyLocally) {
        try {
          await api.addReaction(post.id, key)
        } catch (err) {
          // failed? remove it again
          this.reactionRemove(post, key, true)
          pulseError(this.$i18n('error_unexpected'))
        }
      }
    },
    async reactionRemove (post, key, onlyLocally = false) {
      const reactionUser = post.reactions[key].find(r => r.id === this.userId)

      if (!reactionUser) return

      post.reactions[key].splice(post.reactions[key].indexOf(reactionUser), 1)

      if (!onlyLocally) {
        try {
          await api.removeReaction(post.id, key)
        } catch (err) {
          // failed? add it again
          this.reactionAdd(post, key, true)
          pulseError(this.$i18n('error_unexpected'))
        }
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
          name: `${this.userFirstName} ${DataUser.getters.getUserLastName()}`,
          avatar: DataUser.getters.getAvatar(),
        },
      }
      this.loadingPosts.push(-1)
      this.posts.push(dummyPost)

      try {
        await api.createPost(this.id, body)
        await api.followThreadByBell(this.id)
        await this.reload()
      } catch (err) {
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
        pulseError(this.$i18n('error_unexpected'))
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
        pulseError(this.$i18n('error_unexpected'))
      }
    },
    async updateClosed () {
      this.isLoading = true
      const targetStatus = [ThreadStatus.THREAD_CLOSED, ThreadStatus.THREAD_OPEN][this.status]
      try {
        await api.setThreadStatus(this.id, targetStatus)
        this.status = targetStatus
      } catch (err) {
        pulseError(this.$i18n('error_unexpected'))
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
        pulseError(this.$i18n('error_unexpected'))
      }
      this.isLoading = false
    },
    updatePriority () {
      this.newPriority = this.stickiness
      this.$refs.priorityEditModal.show()
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

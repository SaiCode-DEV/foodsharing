<template>
  <div
    :class="{disabledLoading: isLoading}"
    class="bootstrap"
  >
    <div class="d-flex rounded text-white bg-primary p-2">
      <h4 class="flex-grow-1 wrap-before-overflow" :class="{'text-truncate': title.length > 150}">
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
      </h4>
      <OverflowMenu
        variant="light"
        :options="overflowMenuOptions"
      />
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
    <b-alert
      v-if="linkedReport && !isLoading"
      variant="info"
      class="d-flex justify-content-between align-items-center"
      show
    >
      <div>
        {{ $t('reports.report_id') }}: {{ linkedReport.id }}
      </div>

      <div>
        {{ $t('reports.status') }}:
        <b-badge
          v-if="linkedReport.status"
          :variant="getStatusVariant(linkedReport.status)"
        >
          {{ isTranslatableStatus(linkedReport.status) ? $t(linkedReport.status) : linkedReport.status }}
        </b-badge>
        <span v-else class="text-muted">{{ $t('reports.no_status') }}</span>
      </div>

      <b-button
        variant="outline-primary"
        size="sm"
        @click="openLinkedReportEditModal"
      >
        <i class="fas fa-edit mr-1" /> {{ $t('button.edit') }}
      </b-button>
    </b-alert>

    <ReportEditModal
      v-if="linkedReport"
      :report="linkedReport"
      :show="showLinkedReportEditModal"
      :region-id="regionId"
      :may-delete="false"
      @update="updateLinkedReport"
      @close="closeLinkedReportEditModal"
    />
    <div id="posts-wrapper">
      <div v-for="post in shownPosts" :key="post.id">
        <ThreadPost
          :post="post"
          :user-id="userId"
          :deep-link="getPostLink(post.id)"
          :may-hide="mayHidePosts"
          :may-moderate="mayModerate"
          :may-delete="mayDelete"
          :is-loading="loadingPosts.indexOf(post.id) != -1"
          :created-at="new Date(post.createdAt)"
          :may-reply="isOpen"
          :is-linked="linkedPost == post.id"
          :edit-remaining-seconds="post.id === lastPostId ? editRemainingSeconds : 0"
          @delete="deletePost(post)"
          @hide="hidePost(post.id, $event)"
          @reaction-add="key => addReaction(post.id, key)"
          @reaction-remove="key => removeReaction(post.id, key)"
          @reply="reply(post, true)"
          @reply-full="reply(post, false)"
          @restore="restorePost(post.id)"
          @edit="openEditModal(post)"
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

    <b-modal
      v-if="editingPost"
      ref="editModal"
      centered
      :title="$t('forum.post.edit')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.save')"
      :ok-disabled="editRemainingSeconds <= 0"
      @ok="submitEdit"
      @hidden="closeEditModal"
    >
      <div class="mb-2 text-muted small">
        <span v-if="editRemainingSeconds > 0">
          <i class="far fa-clock mr-1" />
          {{ $t('forum.post.edit_time_remaining', { time: formatRemaining(editRemainingSeconds) }) }}
        </span>
        <span v-else class="text-danger">
          {{ $t(editConflict ? 'forum.post.edit_conflict' : 'forum.post.edit_expired') }}
        </span>
      </div>
      <MarkdownInput
        :value="editText"
        :rows="6"
        @update:value="newValue => editText = newValue"
      />
      <div class="mt-2 text-muted small">
        {{ $t('forum.post.edit_mentions_note') }}
      </div>
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
import { pulseError, pulseWarning, pulseSuccess } from '@/script'
import { useUserStore } from '@/stores/user'
import JumpScrollButton from '@/components/JumpScrollButton.vue'
import ReportEditModal from '@/components/Report/ReportEditModal.vue'
import SubscribeButton from './SubscribeButton.vue'
import ThreadForm from './ThreadForm'
import ThreadPost from './ThreadPost'
import ThreadStatus from './ThreadStatus'
import HiddenPostsAlert from './HiddenPostsAlert'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'
import Info from '@/components/Help/Info.vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import { sameRouteNavigationEvent } from '@/helper/router'

export default {
  components: { ThreadForm, ThreadPost, OverflowMenu, JumpScrollButton, SubscribeButton, HiddenPostsAlert, VueSlider, Info, MarkdownInput, ReportEditModal },
  props: {
    id: {
      type: Number,
      default: null,
    },
  },
  setup () {
    const userStore = useUserStore()
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
      creatorId: null,

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
      lastPostId: null,
      editRemainingSeconds: 0,
      editTimeout: null,
      editCountdown: null,
      editingPost: null,
      editText: '',
      editConflict: false,
      newTitle: '',
      newPriority: 0,
      linkedPost: null,
      linkedReport: null,
      showLinkedReportEditModal: false,
      showHiddenPosts: false,

      status: ThreadStatus.THREAD_OPEN,
    }
  },
  computed: {
    userId () {
      return this.userStore.getUserId
    },
    userFirstName () {
      return this.userStore.getUserFirstName
    },
    isOpen () {
      return this.status === ThreadStatus.THREAD_OPEN
    },
    mayRename () {
      return this.mayModerate || this.userId === this.creatorId
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
  watch: {
    // Client-side navigation can leave this component mounted while only the `pid` query
    // parameter changes (e.g. clicking another "new post" bell notification for a thread
    // that is already open), so the linked post also has to be re-evaluated reactively
    // instead of only once in created().
    '$route.query.pid' () {
      this.showLinkedPost()
    },
  },
  async created () {
    this.isLoading = true
    // Clicking the link to the post that is already linked in the url does not
    // change the route, so the watcher above cannot pick it up.
    window.addEventListener(sameRouteNavigationEvent, this.onSameRouteNavigation)
    await this.reload()
    await new Promise(resolve => window.setTimeout(resolve, 200))
    this.showLinkedPost()
  },
  beforeDestroy () {
    window.removeEventListener(sameRouteNavigationEvent, this.onSameRouteNavigation)
    clearTimeout(this.editTimeout)
    clearInterval(this.editCountdown)
  },
  methods: {
    onSameRouteNavigation () {
      // The url did not change, but the thread content may still have changed since it
      // was loaded, so a repeated click on the same link has to refetch it.
      this.showLinkedPost(true)
    },
    // A deep link that arrives (or is clicked again) while the thread is already open.
    async showLinkedPost (forceReload = false) {
      const pid = parseInt(GET('pid'), 10)
      if (Number.isNaN(pid)) return
      this.linkedPost = pid
      // The linked post is usually missing because it was written after the thread was
      // loaded (e.g. a bell for a new post in the thread that is already open), so
      // refetch the posts before deciding that it cannot be found.
      if (forceReload || !this.posts.some(post => post.id === pid)) {
        await this.reload()
        await this.$nextTick()
      }
      // The posts are ordered by time, so the next best post after a deleted one
      // has to be picked by id instead of by list position.
      const linkedPost = this.posts.find(post => post.id === pid)
      const nextPost = this.posts
        .filter(post => post.id > pid)
        .reduce((closest, post) => (!closest || post.id < closest.id ? post : closest), null)
      this.scrollToPost(linkedPost ?? nextPost, pid)
    },
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
        const res = await api.getThread(this.id)
        Object.assign(this, {
          title: res.title,
          regionId: res.regionId,
          regionSubId: res.subforumId,
          posts: res.posts,
          lastPostId: res.lastPostId,
          stickiness: res.pinnedLevel,
          isActive: res.isActive,
          mayModerate: res.permissions.mayModerate,
          mayDelete: res.permissions.mayDelete,
          mayHidePosts: res.permissions.mayHidePosts,
          isFollowingEmail: res.subscriptionsStatus.isMailSubscribed,
          isFollowingBell: res.subscriptionsStatus.isBellSubscribed,
          status: +res.isLocked,
          creatorId: res.creatorId,
          linkedReport: res.linkedReport || null,
        })
        this.computeEditRemaining()
        this.isLoading = false
      } catch (err) {
        if (err.code === HTTP_RESPONSE.NOT_FOUND || err.code === HTTP_RESPONSE.FORBIDDEN) {
          // The thread does not exist or deleted -> reload so PHP route can redirect to the forum overview
          window.location.reload()
        } else if (!isDeleteAction) {
          this.isLoading = false
          this.errorMessage = err.message
        } else {
          // In this case the last post was deleted.
          window.location = this.$url('forum', this.regionId)
        }
      }
    },
    computeEditRemaining () {
      clearTimeout(this.editTimeout)
      const editableWindow = 600 // seconds
      const lastPost = this.posts?.find(p => p.id === this.lastPostId)
      if (lastPost && String(this.userId) === String(lastPost.author?.id)) {
        const created = new Date(lastPost.createdAt).getTime()
        this.editRemainingSeconds = Math.max(0, editableWindow - Math.floor((Date.now() - created) / 1000))
      } else {
        this.editRemainingSeconds = 0
      }
      if (this.editRemainingSeconds > 0) {
        this.editTimeout = setTimeout(() => { this.editRemainingSeconds = 0 }, this.editRemainingSeconds * 1000)
      }
    },
    openEditModal (post) {
      this.editingPost = post
      this.editText = post.body || ''
      // Recalculate from actual post creation time so reopening the modal always shows the correct value
      const editableWindow = 600
      const created = new Date(post.createdAt).getTime()
      this.editRemainingSeconds = Math.max(0, editableWindow - Math.floor((Date.now() - created) / 1000))
      this.editConflict = false
      this.$nextTick(() => this.$refs.editModal.show())
      this.editCountdown = setInterval(() => {
        this.editRemainingSeconds = Math.max(0, this.editRemainingSeconds - 1)
        if (this.editRemainingSeconds === 0) {
          clearInterval(this.editCountdown)
          this.editCountdown = null
        }
      }, 1000)
    },
    closeEditModal () {
      clearInterval(this.editCountdown)
      this.editCountdown = null
    },
    formatRemaining (seconds) {
      if (!seconds || seconds <= 0) return '00:00'
      const m = Math.floor(seconds / 60)
      const s = Math.floor(seconds % 60)
      return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    },
    async submitEdit (evt) {
      evt?.preventDefault()
      try {
        await api.editPost(this.editingPost.id, this.editText.trim())
        pulseSuccess(this.$t('forum.post.edit_success'))
        this.$refs.editModal.hide()
        await this.reload()
      } catch (err) {
        const serverMsg = err?.jsonContent?.error || err?.jsonContent?.message
        if (serverMsg === 'Edit window expired for this post') {
          pulseError(this.$t('forum.post.edit_expired'))
        } else if (serverMsg === 'Cannot edit as another post was added meanwhile') {
          pulseError(this.$t('forum.post.edit_conflict'))
          this.editConflict = true
          this.editRemainingSeconds = 0
          clearInterval(this.editCountdown)
          this.editCountdown = null
        } else if (serverMsg === 'Thread not found') {
          pulseError(this.$t('forum.post.edit_thread_not_found'))
        } else if (serverMsg === 'Post not found') {
          pulseError(this.$t('forum.post.edit_post_not_found'))
        } else {
          pulseError(this.$t('error_unexpected') + (serverMsg ? `: ${serverMsg}` : ''))
        }
        console.error(err)
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
              name: this.userStore.getUserFirstName,
              id: this.userStore.getUserId,
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
        body,
        reactions: {},
        author: {
          name: this.userFirstName,
          avatar: this.userStore.getAvatar,
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
    openLinkedReportEditModal () {
      this.showLinkedReportEditModal = true
    },
    closeLinkedReportEditModal () {
      this.showLinkedReportEditModal = false
    },
    updateLinkedReport (updateData) {
      if (!this.linkedReport) return

      const nextForumThreadId = Object.prototype.hasOwnProperty.call(updateData, 'forumThreadId')
        ? updateData.forumThreadId
        : this.linkedReport.forumThreadId

      if (nextForumThreadId !== this.id) {
        this.linkedReport = null
        return
      }

      this.linkedReport = {
        ...this.linkedReport,
        ...updateData,
      }
      this.showLinkedReportEditModal = false
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
    isTranslatableStatus (status) {
      return status && (status.startsWith('reports.statuses.') || status.startsWith('reports.consequences.'))
    },
    getStatusVariant (status) {
      const variants = {
        'reports.statuses.to_do': 'danger',
        Offen: 'danger',
        'reports.statuses.handed_over': 'info',
        Abgegeben: 'info',
        'reports.statuses.completed': 'success',
        Abgeschlossen: 'success',
        'reports.statuses.mediation': 'info',
        Mediationsgruppe: 'info',
        'reports.statuses.in_progress': 'warning',
        'In Bearbeitung': 'warning',
        'reports.statuses.follow_up_user': 'info',
        'Rückfrage an User': 'info',
        'reports.statuses.reminder': 'info',
        'reports.statuses.deleted': 'dark',
        Gelöscht: 'dark',
      }
      return variants[status] || 'secondary'
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

.wrap-before-overflow {
  overflow-wrap: anywhere;
}
</style>

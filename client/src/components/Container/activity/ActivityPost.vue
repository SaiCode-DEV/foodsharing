<template>
  <li
    class="list-group-item activity-post"
  >
    <div
      class="d-flex align-items-center mb-2 font-weight-bold"
    >
      <a
        v-if="fs_id && fs_name"
        :href="$url('profile', fs_id)"
        class="d-flex align-items-center"
      >
        <span>{{ fs_name }}</span>
      </a>
      <span v-else-if="fs_id">
        {{ $i18n('dashboard.deleted_user') }}
      </span>
      <a
        v-else-if="sender_email"
        v-b-tooltip="sender_email.length > 25 ? sender_email : null"
        :href="dashboardContentLink"
        class="d-inline-block text-truncate"
        style="max-width: 125px;"
        v-html="sender_email"
      />
      <i
        v-if="type !== 'friendWall'"
        class="text-muted fas fa-angle-right mr-1 ml-1"
      />
      <a
        v-if="type !== 'friendWall'"
        v-b-tooltip="title.length > 100 ? title : null"
        :href="dashboardContentLink"
        class="d-inline-block text-truncate"
        v-html="title"
      />
    </div>

    <div class="d-flex mb-2 text-break">
      <a
        v-b-tooltip="fs_id ? fs_name : sender_email"
        :href="fs_id ? $url('profile', fs_id) : dashboardContentLink"
        class="icon w-20 mr-2 d-flex text-center justifiy-content-center align-items-center align-self-start"
      >
        <Avatar
          v-if="fs_id"
          class="img-thumbnail"
          :url="icon"
          :size="50"
        />
        <i
          v-else
          class="d-flex text-secondary img-thumbnail w-100 h-100 align-items-center justify-content-center"
          :class="icon"
        />
        <span
          class="sr-only"
          v-html="fs_id ? fs_name : sender_email"
        />
      </a>
      <div class="content">
        <div
          v-if="gallery.length > 0"
          class="d-inline-flex mb-3"
        >
          <a
            v-for="img in gallery.slice(0,4)"
            :key="img.thumb"
            :href="dashboardContentLink"
            class="img-thumbnail mr-1"
          >
            <img
              :alt="$i18n('upload.preview_image')"
              :src="img.thumb"
              loading="lazy"
            >
          </a>
        </div>
        <Markdown
          :source="!state ? truncate(desc, truncatedLength) : desc"
        />
        <button
          v-if="isTruncatable || canQuickreply"
          class="btn btn-sm btn-link ml-n2 mb-1"
          @click.stop.prevent="toggleState"
        >
          <span
            v-if="isTruncatable"
            v-text="!state ? $i18n('globals.show_more') : $i18n('globals.show_less')"
          />
          <span
            v-if="isTruncatable && canQuickreply"
            v-text="!state ? '&' : ''"
          />
          <span
            v-if="canQuickreply && isTruncatable"
            v-text="!state ? $i18n('activitypost.response') : ''"
          />
          <span
            v-if="canQuickreply && !isTruncatable"
            v-text="!state ? $i18n('activitypost.Response') : $i18n('globals.show_less')"
          />
          <i
            :class="{ 'fa-rotate-180': state }"
            class="fas fa-fw fa-angle-down"
          />
        </button>
      </div>
    </div>
    <div
      v-if="canQuickreply && state"
      class="d-flex w-100 flex-column justify-content-center"
    >
      <div
        v-if="!qrLoading"
      >
        <div class="position-relative">
          <textarea
            ref="quickreply"
            v-model="quickreplyValue"
            name="quickreply"
            class="form-control"
            :placeholder="$i18n('activitypost.write')"
            rows="1"
            @click.stop="$refs.quickreply.focus()"
            @keyup="resizeTextarea"
            @keydown.enter.exact.prevent="send()"
          />
          <button
            v-b-tooltip.html="$i18n('activitypost.quickreply_button')"
            class="btn mt-2 btn-primary"
            :class="{
              'position-absolute btn-sm': !viewIsMobile,
              'btn-block': viewIsMobile,
              'btn-outline-primary': !quickreplyValue
            }"
            style="bottom:1rem; right:1rem;"
            :disabled="!quickreplyValue"
            @click="send(true)"
          >
            <i class="fas fa-paper-plane" />
            <span
              v-if="viewIsMobile"
              v-text="$i18n('activitypost.Response')"
            />
          </button>
        </div>
        <small
          v-if="!viewIsMobile"
          class="d-inline-flex align-items-center mt-2 text-muted"
        >
          <i class="fas fa-info-circle mr-1" />
          <span v-html="$i18n('activitypost.quickreply_info')" />
        </small>
      </div>
      <span
        v-else
        class="loader"
      >
        <i class="fas fa-spinner fa-spin" />
      </span>
    </div>

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mt-2">
      <small class="text-muted order-2 order-sm-1">
        <i class="far fa-fw fa-clock" />
        <span v-b-tooltip="$dateFormatter.dateTimeTooltip(time)"> {{ $dateFormatter.base(time) }} </span>
      </small>
      <small
        v-if="source"
        v-b-tooltip="source.length > 40 ? source : null"
        class="text-truncate order-1 order-sm-2 mb-0"
        v-html="$i18n(translationKey, [source])"
      />
    </div>
  </li>
</template>

<script>
import StateTogglerMixin from '@/mixins/StateTogglerMixin'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import AutoResizeTextareaMixin from '@/mixins/AutoResizeTextareaMixin'

import { pulseInfo } from '@/script'
import { createPost } from '@/api/forum'
import { addPost } from '@/api/wall'

import Markdown from '@/components/Markdown/Markdown'
import Avatar from '@/components/Avatar'
import { sendEmail } from '@/api/mailbox'

export default {
  components: { Markdown, Avatar },
  mixins: [StateTogglerMixin, MediaQueryMixin, AutoResizeTextareaMixin],
  /* eslint-disable vue/prop-name-casing */
  props: {
    // Shared properties
    time: { type: Date, required: true },

    type: { type: String, required: true },
    desc: { type: String, default: '' },
    title: { type: String, default: '' },

    icon: { type: String, default: '' },
    source: { type: String, default: '' },
    source_suffix: { type: String, default: '' },
    gallery: { type: Array, default: () => { return [] } },
    quickreply: { type: String, default: '' },

    fs_id: { type: Number, default: null },
    fs_name: { type: String, default: '' },
    region_id: { type: Number, default: null },
    entity_id: { type: Number, default: null },

    // Individual update-type properties for forum posts: ActivityUpdateForum
    forum_post: { type: Number, default: null },
    forum_type: { type: String, default: '' },

    // Individual update-type properties for mailboxes: ActivityUpdateMailbox
    sender_email: { type: String, default: '' },
    mailboxId: { type: Number, default: null },
  },
  /* eslint-enable */
  data () {
    return {
      truncatedLength: 280,
      isTruncatedText: true,
      qrLoading: false,
      quickreplyValue: '',
    }
  },
  computed: {
    dashboardContentLink () {
      switch (this.type) {
        case 'event':
          return this.$url('event', this.entity_id)
        case 'foodsharepoint':
          return this.$url('foodsharepoint', this.entity_id)
        case 'friendWall':
          return this.$url('profile', this.fs_id)
        case 'forum':
          return this.$url('forum', this.region_id, (this.forum_type === 'botforum'), this.entity_id, this.forum_post)
        case 'mailbox':
          return this.$url('mailbox', this.entity_id)
        case 'store':
          return this.$url('store', this.entity_id)
        default:
          return '#'
      }
    },
    isTruncatable () {
      return this.desc.length > this.truncatedLength
    },
    translationKey () {
      return 'dashboard.source_' + this.type + this.source_suffix
    },
    canQuickreply () {
      return ['forum', 'event', 'mailbox'].includes(this.type)
    },
    isReplyEmpty () {
      return (
        this.quickreplyValue.trim() === null ||
        this.quickreplyValue.trim().length === 0
      )
    },
  },
  methods: {
    truncate (str, maxLength = 30) {
      if (str.length > maxLength) {
        return str.substring(0, maxLength) + '...'
      }
      return str
    },
    newLine () {
      this.quickreplyValue += '\n'
    },
    formatReplyBody () {
      const date = this.$dateFormatter.format(this.dateObject, {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
      })
      return this.quickreplyValue +
        '\n\n\n\n--------- ' +
        this.$i18n('mailbox.signature', { date: date }) +
        ' ---------\n\n>\t' +
        this.desc.replace('\n', '\n>\t')
    },
    async send (forced = false) {
      if ((this.viewIsMD && !this.isReplyEmpty) || (forced && !this.isReplyEmpty)) {
        this.qrLoading = true
        try {
          // forum posts and wall posts already use the REST API for quickreplies
          if (this.type === 'forum') {
            await createPost(this.entity_id, this.quickreplyValue)
            pulseInfo(this.$i18n('forum.quickreply.success'))
          } else if (this.type === 'event') {
            await addPost('event', this.entity_id, this.quickreplyValue)
            pulseInfo(this.$i18n('forum.quickreply.success'))
          } else if (this.type === 'mailbox') {
            const subject = 'Re: ' + this.title
            const to = [this.sender_email]
            const body = this.formatReplyBody()
            await sendEmail(this.mailboxId, to, null, null, subject, body, null, this.entity_id)
            pulseInfo(this.$i18n('mailbox.okay'))
          }
          this.quickreplyValue = ''
        } catch (e) {
          console.error(e)
          pulseInfo(this.$i18n('forum.quickreply.error'))
        } finally {
          this.qrLoading = false
        }
      } else {
        this.newLine()
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.form-control {
  min-height: 6rem;
  overflow: hidden;
  cursor: text;
}

::v-deep.markdown {
  p {
    font-size: 15px;
    color: var(--fs-color-dark);
    line-height: 1.5;
  }

  p:last-child {
    margin-bottom: 0;
  }
}

</style>

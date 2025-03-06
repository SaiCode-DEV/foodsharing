<template>
  <div>
    <div class="emojis mb-1 d-inline-block">
      <b-dropdown
        v-if="canGiveEmoji"
        ref="emojiSelector"
        v-b-tooltip.hover
        :title="$i18n('addreaction')"
        text="+"
        class="emoji-dropdown"
        size="sm"
        no-caret
        right
      >
        <a
          v-for="(symbol, key) in emojisToGive"
          :key="key"
          class="btn"
          @click="giveEmoji(key)"
        >
          <Emoji :name="key" />
        </a>
      </b-dropdown>
      <span
        v-for="(users, key) in reactionsWithUsers"
        :key="key"
      >
        <b-button
          :id="`reactionButton-${key}-${uuid}`"
          class="btn-sm"
          :variant="gaveIThisReaction(key) ? 'secondary' : 'primary'"
          @click="toggleReaction(key)"
        >
          {{ users.length }}x <Emoji :name="key" />
        </b-button>
        <b-tooltip
          :target="`reactionButton-${key}-${uuid}`"
          triggers="hover"
        >
          <span
            v-for="user in users"
            :key="user.id"
            class="reacting-user"
          >
            <a :href="$url('profile', user.id)" v-text="tooltipName(user)" />
          </span>
        </b-tooltip>
      </span>
    </div>

    <span
      v-if="mayReply || mayDelete"
      class="divider text-black-50 mx-1"
    />
    <a
      v-if="mayReply"
      class="btn btn-sm btn-primary"
      @click="$emit('reply')"
    >
      {{ $i18n('button.answer') }}
    </a>
    <a
      v-if="mayHide"
      v-b-tooltip="$i18n('forum.post.hide')"
      class="btn btn-sm btn-danger"
      @click="$refs.hideModal.show()"
    >
      <i class="fas fa-eye-slash" />
    </a>
    <a
      v-if="mayDelete"
      v-b-tooltip.hover
      :title="$i18n('forum.post.delete')"
      class="btn btn-sm btn-danger"
      @click="$refs.confirmDelete.show()"
    >
      <i class="fas fa-trash-alt" />
    </a>

    <b-modal
      v-if="mayDelete"
      ref="confirmDelete"
      :title="$i18n('forum.post.delete')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      ok-variant="outline-danger"
      centered
      @ok="$emit('delete')"
    >
      <p>{{ $i18n('really_delete') }}</p>
    </b-modal>

    <b-modal
      v-if="mayHide"
      ref="hideModal"
      :title="$i18n('forum.post.sureHide')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      ok-variant="outline-danger"
      centered
      :ok-disabled="!hideReason"
      @ok="$emit('hide', hideReason)"
    >
      <b-form-group :label="$i18n('forum.post.giveHideReason')">
        <b-form-textarea
          v-model="hideReason"
          :state="hideReason ? null : false"
          :placeholder="$i18n('forum.post.hideReasonPlaceholder')"
          :maxlength="255"
        />
      </b-form-group>

      <b-alert show>
        <i class="fas fa-info-circle" />
        {{ $i18n('forum.post.hideInfo') }}
      </b-alert>
    </b-modal>
  </div>
</template>

<script>
import Emoji from '@/components/Emoji'
import emojiList from '@/emojiList.json'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

export default {
  components: { Emoji },
  props: {
    reactions: {
      type: Object,
      default: () => ({}),
    },
    mayDelete: { type: Boolean, default: false },
    mayHide: { type: Boolean, default: false },
    /**
     * Whether the user can write a reply or send emoji reactions. This is disabled in closed threads.
     */
    mayReply: { type: Boolean, default: true },
  },
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      emojis: emojiList,
      hideReason: '',
      uuid: (Math.random().toString(36).slice(2, 10)),
    }
  },
  computed: {
    reactionsWithUsers () {
      // https://github.com/you-dont-need/You-Dont-Need-Lodash-Underscore#_pickby
      const reactArr = Object.entries(this.reactions)
      const filtered = reactArr.filter(([_, reaction]) => reaction.length > 0)
      return Object.fromEntries(filtered)
    },
    canGiveEmoji () {
      return Object.keys(this.emojisToGive).length > 0
    },
    emojisToGive () {
      // https://github.com/you-dont-need/You-Dont-Need-Lodash-Underscore#_pickby
      const emojisArr = Object.entries(this.emojis)
      const filtered = emojisArr.filter(([emoji]) => !this.gaveIThisReaction(emoji))
      return Object.fromEntries(filtered)
    },
  },
  methods: {
    toggleReaction (key, dontRemove = false) {
      if (this.gaveIThisReaction(key)) {
        if (!dontRemove) {
          this.$emit('reaction-remove', key)
        }
      } else {
        this.$emit('reaction-add', key)
      }
    },
    giveEmoji (key) {
      this.$refs.emojiSelector.hide()
      this.toggleReaction(key, true)
    },
    gaveIThisReaction (key) {
      if (!this.reactions[key]) {
        return false
      }
      return !!this.reactions[key].find(r => r.id === userStore.getUserId)
    },
    tooltipName (user) {
      if (user.id === userStore.getUserId) return this.$i18n('globals.you')
      return user.name ?? this.$i18n('forum.deleted_user')
    },
  },
}
</script>

<style lang="scss" scoped>
.emoji-dropdown {
  .dropdown-menu .btn {
    padding: 0;

    .emoji {
      padding: 0 0.3em;
    }
  }
}

.emojis {
  line-height: 2.5;

  span > a {
    margin-left: 3px;

    span {
      line-height: 1;
      font-size: 1.35em;
      vertical-align: middle;
    }
  }
}

.divider {
  &::before {
    content: '|';
  }
}

.tooltip .reacting-user{
  a {
    color: white !important;
    font-weight: normal;
  }
  &:not(:last-child)::after {
    content: ', ';
  }
  &:nth-last-child(2)::after {
    content: ' & ';
  }
}
</style>

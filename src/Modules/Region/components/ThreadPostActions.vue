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
        <b-link
          v-b-tooltip="concatUsers(users)"
          class="btn btn-sm"
          :class="[gaveIThisReaction(key) ? 'btn-secondary' : 'btn-primary']"
          @click="toggleReaction(key)"
        >
          {{ users.length }}x <Emoji :name="key" />
        </b-link>
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
      cancel-variant="primary"
      ok-variant="outline-danger"
      modal-class="bootstrap"
      @ok="$emit('delete')"
    >
      <p>{{ $i18n('really_delete') }}</p>
    </b-modal>
  </div>
</template>

<script>
import { BDropdown, BModal, VBTooltip, BLink } from 'bootstrap-vue'

import Emoji from '@/components/Emoji'
import emojiList from '@/emojiList.json'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

export default {
  components: { BDropdown, Emoji, BModal, BLink },
  directives: { VBTooltip },
  props: {
    reactions: {
      type: Object,
      default: () => ({}),
    },
    mayDelete: {
      type: Boolean,
      default: false,
    },
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
    concatUsers (users) {
      const names = users.map(u => u.id === userStore.getUserId ? this.$i18n('globals.you') : u.name ?? this.$i18n('forum.deleted_user'))
      return names.length > 1 ? `${names.slice(0, names.length - 1).join(', ')} & ${names[names.length - 1]}` : names[0]
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
</style>

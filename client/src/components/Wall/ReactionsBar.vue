<template>
  <b-button-toolbar class="d-inline-flex" style="gap:0.25em">
    <b-dropdown
      v-if="!allReactionsUsed"
      ref="emojiSelector"
      size="sm"
      no-caret
      :variant="showUnobtrusive ? 'invalid' : 'primary'"
      :class="{ 'emoji-dropdown': true, 'unobtrusive': showUnobtrusive }"
      right
    >
      <template #button-content>
        <i :class="`add-reaction-icon fas fa-${hasAnyReactions ? 'plus' : 'face-grin'} ${showUnobtrusive ? 'text-muted' : ''}`" />
      </template>
      <a
        v-for="emoji in unusedReactions"
        :key="emoji + '-new'"
        class="btn px-2 py-0"
        @click="toggleReaction(emoji)"
      >
        <Emoji :name="emoji" />
      </a>
    </b-dropdown>
    <b-button-group class="reactions-bar" size="sm">
      <b-button
        v-for="(users, emoji) in currentReactions"
        :id="`reactionButton-${emoji}-${uuid}`"
        :key="emoji + '-add'"
        :variant="hasUserReacted[emoji] ? 'secondary' : 'primary'"
        @click="toggleReaction(emoji)"
      >
        <span v-text="users.length" />
        <Emoji :name="emoji" />
      </b-button>
      <b-tooltip
        v-for="(users, emoji) in currentReactions"
        :key="emoji + '-tooltip'"
        :target="`reactionButton-${emoji}-${uuid}`"
        triggers="hover"
      >
        <!-- No whitespace inside the span: it would end up in front of the
             separator that ::after appends, as "Name , Name". -->
        <span
          v-for="user in users"
          :key="user.id"
          class="reacting-user"
        ><router-link :to="$url('profile', user.id)">{{ tooltipName(user) }}</router-link></span>
      </b-tooltip>
    </b-button-group>
  </b-button-toolbar>
</template>
<script>
import Emoji from '../Emoji.vue'
import emojiList from '@/emojiList.json'
import { useUserStore } from '@/stores/user'
import { objectMap } from '@/utils'

export default {
  components: { Emoji },
  props: {
    reactions: { type: [Object, Array], default: () => {} },
    unobtrusive: { type: Boolean, default: false },
  },
  setup () {
    return { userStore: useUserStore() }
  },
  data () {
    return {
      currentReactions: { ...this.reactions },
      uuid: (Math.random().toString(36).slice(2, 10)),
    }
  },
  computed: {
    usedReactions () {
      return Object.keys(this.currentReactions)
    },
    unusedReactions () {
      const used = new Set(this.usedReactions)
      const all = Object.keys(emojiList)
      return all.filter(reaction => !used.has(reaction))
    },
    hasUserReacted () {
      return objectMap(this.currentReactions, (users, emoji) => users.some(this.isMe))
    },
    hasAnyReactions () {
      return this.usedReactions.length > 0
    },
    allReactionsUsed () {
      return this.usedReactions.length >= Object.keys(emojiList).length
    },
    showUnobtrusive () {
      return !this.hasAnyReactions && this.unobtrusive
    },
  },
  methods: {
    toggleReaction (key) {
      this.$refs.emojiSelector?.hide?.()
      if (this.hasUserReacted[key]) {
        this.$emit('reaction-remove', key)
        const index = this.currentReactions[key].findIndex(this.isMe)
        this.currentReactions[key].splice(index, 1)
        if (!this.currentReactions[key].length) this.$delete(this.currentReactions, key)
      } else {
        this.$emit('reaction-add', key)
        if (!(key in this.currentReactions)) this.$set(this.currentReactions, key, [])
        this.currentReactions[key].push({ id: this.userStore.getUserId, name: this.userStore.getUserFirstName })
      }
    },
    isMe (user) {
      return user.id === this.userStore.getUserId
    },
    tooltipName (user) {
      if (user.id === this.userStore.getUserId) return this.$t('globals.you')
      return user.name ?? this.$t('forum.deleted_user')
    },
  },
}
</script>
<style lang="scss">

.emoji-dropdown .dropdown-menu {
  min-width: max-content;
  padding-left: 1em;
  padding-right: 1em;
}
.emoji-dropdown .dropdown-item {
  padding: 0;
  width: auto;
}

.emoji {
  line-height: 1;
  font-size: 1.35em;
  vertical-align: text-bottom;
}

.add-reaction-icon {
  font-size: 1.3em;
  line-height: 1em;
  vertical-align: text-top;
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

.unobtrusive .btn {
  margin-right: -0.65em;
  padding: 0 0.25em;
  .add-reaction-icon {
    font-size: 1em;
  }
}
</style>

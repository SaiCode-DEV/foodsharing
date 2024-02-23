<template>
  <div class="list-group bg-white mb-2">
    <div
      class="list-group-item list-group-header"
      @click="collapsible ? toggleExpanded() : null"
    >
      <h5
        :class="{ 'expanded': isExpanded }"
        v-text="title"
      />
      <i
        v-if="collapsible"
        :id="`expand-${title}`"
        :alt="isExpanded ? $i18n('globals.show_more') : $i18n('globals.show_less')"
        class="fas fa-angle-down"
        :class="{ 'fa-rotate-180': isExpanded }"
      />
    </div>
    <slot v-if="isExpanded" />
    <button
      v-if="isExpanded && isToggleVisible && !isToggled"
      class="list-group-item small list-group-item-secondary list-group-item-action list-group-item-action-toggle font-weight-bold text-center"
      @click="showFullList"
      v-text="$i18n('globals.show_more')"
    />
    <button
      v-else-if="isExpanded && isToggled"
      class="list-group-item small list-group-item-action list-group-item-action-toggle font-weight-bold text-center"
      @click="reduceList"
      v-text="$i18n('globals.show_less')"
    />
  </div>
</template>

<script>

export default {
  name: 'ToggleContainer',
  props: {
    tag: { type: String, default: 'tag' },
    title: { type: String, default: 'title' },
    toggleVisiblity: { type: Boolean, default: false },
    containerIsExpanded: { type: Boolean, default: true },
    collapsible: { type: Boolean, default: true },
  },
  data () {
    return {
      isToggled: false,
      isExpanded: this.containerIsExpanded,
    }
  },
  computed: {
    isToggleVisible () {
      return this.toggleVisiblity
    },
  },
  created () {
    const state = this.getExpanded()
    if (state !== null) {
      this.setExpanded(state)
    }

    const listState = this.getListState()
    if (listState !== null) {
      this.setListState(listState)
    }
  },
  methods: {
    toggleExpanded () {
      this.setExpanded(!this.isExpanded)
    },
    getExpanded () {
      return JSON.parse(localStorage.getItem(`expanded_${this.tag}`))
    },
    setExpanded (state) {
      this.isExpanded = state
      localStorage.setItem(`expanded_${this.tag}`, JSON.stringify(state))
    },
    showFullList () {
      this.setListState(true)
    },
    reduceList () {
      this.setListState(false)
    },
    getListState () {
      return JSON.parse(localStorage.getItem(`list_state_${this.tag}`))
    },
    setListState (state) {
      this.isToggled = state
      localStorage.setItem(`list_state_${this.tag}`, JSON.stringify(state))
      this.$emit(state ? 'show-full-list' : 'reduce-list')
    },
  },
}
</script>

<style lang="scss" scoped>
.list-group {
  min-width: 250px;
  margin-bottom: 1rem;

  &:last-child {
    margin-bottom: 0;
  }
}

.list-group-header {
  align-items: center;
  background-color: var(--fs-color-primary-500);
  color: var(--fs-color-primary-100);
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  min-height: 40px;
  padding: 0 1rem;

  &:hover {
    background-color: var(--fs-color-primary-600);
  }

  h5 {
    font-size: 0.8rem;
    font-weight: bold;
  }
}

.list-group-item:not(:last-child):not(.list-group-header):not(.list-row-item) {
  border-bottom: 0;
}

::v-deep .field {
  display: flex;
  min-height: 70px;

  &--stack {
    flex-direction: column;
    justify-content: space-between;
  }
}

::v-deep .field-container {
  display: flex;
  justify-content: space-between;
  width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  align-items: center;

  &--stack {
    flex-direction: column;
    align-items: start;
  }
}

::v-deep .field-headline {
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: bold;
  font-size: 0.9rem;

  &--big {
    font-size: 1rem;
  }
}

::v-deep .field-subline {
  display: inline-block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-right: 0.5rem;

  &--muted {
    color: var(--fs-color-gray-500);
  }
}

.list-group-item-action-toggle {
  border-top-width: 1px;
}
</style>

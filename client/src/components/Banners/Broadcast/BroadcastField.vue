<template>
  <div
    v-if="entry.body && (!isSet || isNew)"
    class="broadcastfield"
  >
    <div class="broadcastfield__content">
      <div class="broadcastfield__content-wrapper">
        <h4
          v-if="entry.title"
          class="broadcastfield__title"
          v-text="entry.title"
        />
        <!-- eslint-disable vue/no-v-html -->
        <!-- Sanitized in src/Modules/Content/ContentGateway.php get() -->
        <div
          v-if="entry.body"
          class="broadcastfield__description"
          v-html="entry.body"
        />
        <!-- eslint-enable -->
      </div>
    </div>
    <i
      class="broadcastfield__close fas fa-times"
      @click="close"
    />
  </div>
</template>

<script>
export default {
  props: {
    entry: {
      type: Object,
      required: true,
    },
  },
  data () {
    return {
      tag: 'broadcast',
    }
  },
  computed: {
    isSet () {
      return JSON.parse(localStorage.getItem(this.tag))
    },

    isNew () {
      const storage = JSON.parse(localStorage.getItem(`${this.tag}_time`))
      return new Date(this.entry.last_mod) > new Date(storage)
    },
  },
  created () {
    if (this.isNew) {
      localStorage.removeItem(this.tag)
      localStorage.removeItem(`${this.tag}_time`)
    }
  },
  mounted () {
    if (this.isSet && !this.isNew) {
      this.remove()
    }
  },
  methods: {
    setSeen () {
      localStorage.setItem(this.tag, JSON.stringify(true))
      localStorage.setItem(`${this.tag}_time`, JSON.stringify(new Date()))
    },
    close () {
      this.setSeen()
      this.remove()
    },
    remove () {
      this.$emit('close')
      this.$destroy()
      this.$el.parentNode.removeChild(this.$el)
    },
  },
}
</script>

<style lang="scss" scoped>
@import "@/scss/bootstrap-theme.scss";

.broadcastfield {
  @extend .alert;

  color: var(--fs-color-info-700);
  background-color: var(--fs-color-info-200);
  border-color: var(--fs-color-info-300);

  display: flex;
  align-items: center;
  justify-content: space-between;
}

.broadcastfield__icon {
  font-size: 2.25rem;
  min-width: 3rem;
  margin-right: 1rem;
  text-align: center;
}

.broadcastfield__content {
  margin-right: auto;
}

.broadcastfield__title {
  margin-top: 0;
  margin-bottom: .25rem;
}

::v-deep.broadcastfield__description *:last-child {
  margin-bottom: 0;
}

.broadcastfield__link {
  @extend .btn;
  @extend .btn-sm;

  color: var(--fs-color-info-100);
  background-color: var(--fs-color-info-500);

  font-weight: 600;

  &:not(:last-child) {
    margin-right: .5rem;
  }

  &:hover {
    color: var(--fs-color-info-100);
    background-color: var(--fs-color-info-600);
  }
}

.broadcastfield__close  {
  cursor: pointer;
  align-self: flex-start;
}
</style>

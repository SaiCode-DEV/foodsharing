<template>
  <div v-if="!isSet" class="informationfield">
    <i
      v-if="entry.icon"
      class="informationfield__icon fas"
      :class="entry.icon"
    />
    <div class="informationfield__content">
      <div class="informationfield__content-wrapper">
        <h4
          class="informationfield__title"
          v-html="$i18n(`information.${entry.field}.title`)"
        />
        <p
          class="informationfield__description"
          v-html="$i18n(`information.${entry.field}.description`)"
        />
      </div>
      <div
        v-if="entry.links.length > 0"
        class="informationfield__links"
      >
        <a
          v-for="(link, key) in entry.links"
          :key="key"
          class="informationfield__link"
          :href="link.urlShortHand ? $url(link.urlShortHand) : link.href"
          v-html="$i18n(link.text)"
        />
      </div>
    </div>
    <i
      class="informationfield__close fas fa-times"
      @click.prevent="close"
    />
  </div>
</template>

<script>
export default {
  props: {
    entry: {
      type: Object,
      default: () => ({
        icon: '',
        field: '',
        links: [],
      }),
    },
  },
  data () {
    return {}
  },
  computed: {
    tag () {
      return this.entry.field
    },
    isSet () {
      return JSON.parse(localStorage.getItem(this.tag)) || false
    },
  },
  async created () {
    if (this.isSet) {
      this.close()
    }
  },
  methods: {
    setSeen () {
      localStorage.setItem(this.tag, JSON.stringify(true))
    },
    close () {
      this.setSeen()
      this.remove()
    },
    remove () {
      this.$emit('close')
      this.$destroy()
      if (this.$el && this.$el?.parentNode) {
        this.$el.parentNode.removeChild(this.$el)
      }
    },
  },
}
</script>

<style lang="scss" scoped>
@import "@/scss/bootstrap-theme.scss";

.informationfield {
  @extend .alert;

  color: var(--fs-color-info-700);
  background-color: var(--fs-color-info-200);
  border-color: var(--fs-color-info-300);

  min-height: 100px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.informationfield__content {
  margin-right: auto;
}

.informationfield__icon {
  font-size: 3rem;
  margin-right: 1rem;
}

.informationfield__title {
  margin-top: 0;
  margin-bottom: .25rem;
}

.informationfield__description {
  margin-bottom: .5rem;
  a {
    text-decoration: underline;
  }
}

.informationfield__link {
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

.informationfield__close  {
  cursor: pointer;
  align-self: flex-start;
}
</style>

<template>
  <div
    v-if="!isSet || isNew"
    class="releasefield"
  >
    <i
      class="releasefield__icon fas fa-magic"
    />
    <div class="releasefield__content">
      <div class="releasefield__content-wrapper">
        <h4
          v-if="ReleaseData.version"
          class="releasefield__title"
          v-text="$i18n(`releases.${ReleaseData.version}`)"
        />
      </div>
      <div class="releasefield__links">
        {{ $i18n('dashboard.release.for_release_notes') }}
        <a
          class="releasefield__link"
          :href="$url('release_notes')"
          v-text="$i18n('menu.entry.release-notes')"
        /><br>
        {{ $i18n('dashboard.release.for_news_from_it') }}
        <a
          class="releasefield__link"
          :href="$url('newsFromIT')"
          target="_blank"
          v-text="$i18n('navigation.news_from_it')"
        />
        <br>
        {{ $i18n('dashboard.release.for_support') }}
        <a
          class="releasefield__link"
          :href="$url('contact')"
          v-text="$i18n('navigation.contact')"
        />
      </div>
    </div>
    <i
      class="releasefield__close fas fa-times"
      @click="close"
    />
  </div>
</template>

<script>
import ReleaseData from './Release.json'

export default {
  data () {
    return {
      ReleaseData,
      tag: 'release_notes',
    }
  },
  computed: {
    isSet () {
      return localStorage.getItem(this.tag)
    },

    isNew () {
      return ReleaseData.version !== localStorage.getItem(this.tag)
    },
  },
  created () {
    if (this.isNew) {
      localStorage.removeItem(this.tag)
    }
  },
  mounted () {
    if (this.isSet && !this.isNew) {
      this.remove()
    }
  },
  methods: {
    setSeen () {
      localStorage.setItem(this.tag, ReleaseData.version)
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

.releasefield {
  @extend .alert;

  color: var(--fs-color-info-700);
  background-color: var(--fs-color-info-200);
  border-color: var(--fs-color-info-300);

  display: flex;
  align-items: center;
  justify-content: space-between;
}

.releasefield__icon {
  font-size: 2.25rem;
  min-width: 3rem;
  margin-right: 1rem;
  text-align: center;
}

.releasefield__content {
  margin-right: auto;
}

.releasefield__title {
  margin-top: 0;
  margin-bottom: .25rem;
}

.releasefield__link {
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

.releasefield__close  {
  cursor: pointer;
  align-self: flex-start;
}
</style>

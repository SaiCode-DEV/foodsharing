<template>
  <div v-if="show" class="releasefield">
    <i class="releasefield__icon fas fa-magic" />
    <div class="mr-auto">
      <div>
        <h4
          v-if="ReleaseData.version"
          class="mt-0 mb-1"
          v-text="$t(`releases.${ReleaseData.version}`)"
        />
        <h6 class="mt-3 mb-2">
          {{ ReleaseData.teaser }}
        </h6>
      </div>
      <div>
        {{ $t('dashboard.release.for_release_notes') }}
        <a
          class="release-link"
          :href="$url('release_notes')"
          v-text="$t('menu.entry.release-notes')"
        />
        <br>

        {{ $t('dashboard.release.for_news_from_it') }}
        <a
          class="release-link"
          :href="$url('newsFromIT')"
          target="_blank"
          v-text="$t('navigation.news_from_it')"
        />
        <br>

        {{ $t('dashboard.release.for_support') }}
        <a
          class="release-link"
          :href="$url('contact')"
          v-text="$t('navigation.contact')"
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
      show: true,
    }
  },
  created () {
    if (this.isSeen() || !this.isRecent()) {
      this.show = false
    }
  },
  methods: {
    isSeen () {
      const seenVersion = localStorage.getItem(this.tag)
      return seenVersion && seenVersion === ReleaseData.version
    },
    setSeen () {
      localStorage.setItem(this.tag, ReleaseData.version)
    },
    isRecent () {
      const releaseDate = new Date(ReleaseData.time)
      const releaseAgeInSeconds = (new Date() - releaseDate) / 1000
      const releaseAgeInDays = releaseAgeInSeconds / (60 * 60 * 24)
      const releaseAgeInWeeks = releaseAgeInDays / 7
      return releaseAgeInWeeks < 4
    },
    close () {
      this.show = false
      this.setSeen()
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

.release-link {
  @extend .btn;
  @extend .btn-sm;

  color: var(--fs-color-info-100);
  background-color: var(--fs-color-info-500);

  font-weight: 600;

  vertical-align: baseline;
  margin-top: 0.25rem;
  margin-right: .25rem;

  &:not(:last-child) {
    margin-bottom: 0.25rem;
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

<template>
  <div class="informationfield">
    <i
      v-if="entry.icon"
      class="information-icon mr-3 fas"
      :class="entry.icon"
    />
    <div class="d-flex flex-column flex-grow-1">
      <div class="d-flex flex-row align-items-start justify-content-between">
        <h4
          class="mt-0 mb-1"
          v-text="$t(`information.${entry.field}.title`)"
        />
        <button
          v-b-tooltip="$t('information.hide')"
          type="button"
          :aria-label="$t('information.hide')"
          class="icon-button"
          @click="$emit('close')"
        >
          <i class="fas fa-times" />
        </button>
      </div>
      <p
        class="mb-2"
        v-text="$t(`information.${entry.field}.description`)"
      />
      <div class="d-flex flex-wrap gap-2">
        <FsLink
          v-for="(link, key) in entry.links"
          :key="key"
          class="information-link"
          :to="link.urlShortHand ? $url(link.urlShortHand) : link.href"
        >
          {{ $t(link.text) }}
        </FsLink>
        <div
          v-if="entryCount > 1"
          class="d-flex flex-row align-items-baseline ml-auto align-self-end"
        >
          <button
            type="button"
            :aria-label="$t('information.previous')"
            class="icon-button"
            @click="$emit('prev')"
          >
            <i class="fas fa-chevron-left" />
          </button>
          <div class="px-2">
            <span aria-hidden="true">
              {{ activeIndex + 1 }}&#x202F;/&#x202F;{{ entryCount }}
            </span>
            <span class="sr-only">
              {{ $t('information.current_index', { current: activeIndex + 1, total: entryCount }) }}
            </span>
          </div>
          <button
            type="button"
            :aria-label="$t('information.next')"
            class="icon-button"
            @click="$emit('next')"
          >
            <i class="fas fa-chevron-right" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import FsLink from '@/components/UI/FsLink.vue'

export default {
  components: { FsLink },
  props: {
    entry: {
      type: Object,
      default: () => ({
        icon: '',
        field: '',
        links: [],
      }),
    },
    activeIndex: {
      type: Number,
      default: 0,
    },
    entryCount: {
      type: Number,
      default: 1,
    },
  },
  emits: ['close', 'prev', 'next'],
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
  margin-bottom: 0;
}

.information-icon {
  font-size: 3rem;
}

.information-link {
  @extend .btn;
  @extend .btn-sm;

  color: var(--fs-color-info-100);
  background-color: var(--fs-color-info-500);

  font-weight: 600;

  &:hover {
    color: var(--fs-color-info-100);
    background-color: var(--fs-color-info-600);
  }
}

.icon-button {
  padding: 0.25rem;
  margin: -0.25rem;
  background: transparent;
  border: none;
}
</style>

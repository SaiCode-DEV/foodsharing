<template>
  <div :class="['errorfield', entry.severity === 'warning' ? 'errorfield--warning' : '']">
    <i
      :class="['errorfield__icon fas', entry.severity === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle']"
    />
    <div class="errorfield__content">
      <div class="errorfield__content-wrapper">
        <h4
          class="errorfield__title"
          v-text="$i18n(`error.${entry.field}.title`)"
        />
        <p
          class="errorfield__description"
          v-text="$i18n(`error.${entry.field}.description`, { link: entry.link, days: entry.days })"
        />
      </div>
      <div
        v-if="entry.links.length > 0"
        class="errorfield__links"
      >
        <a
          v-for="(link, key) in entry.links"
          :key="key"
          class="errorfield__link"
          :href="link.urlShorthand ? $url(link.urlShorthand) : link.href"
          @click="link.modal ? $bvModal.show(link.modal) : null"
          v-text="$i18n(link.text)"
        />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    entry: {
      type: Object,
      default: () => ({
        field: null,
        links: [],
      }),
    },
  },
}
</script>

<style lang="scss" scoped>
@import "@/scss/bootstrap-theme.scss";

.errorfield {
  @extend .alert;

  color: var(--fs-color-danger-700);
  background-color: var(--fs-color-danger-200);
  border-color: var(--fs-color-danger-300);

  min-height: 100px;
  display: flex;
  align-items: center;
}

.errorfield--warning {
  color: var(--fs-color-warning-800);
  background-color: var(--fs-color-warning-200);
  border-color: var(--fs-color-warning-300);
}

.errorfield__icon {
  font-size: 3rem;
  margin-right: 1rem;
}

.errorfield__title {
  margin-top: 0;
  margin-bottom: .25rem;
}

.errorfield__description {
  margin-bottom: .5rem;
  a {
    text-decoration: underline;
  }
}

.errorfield__link {
  @extend .btn;
  @extend .btn-sm;

  color: var(--fs-color-danger-100);
  background-color: var(--fs-color-danger-500);

  font-weight: 600;

  &:not(:last-child) {
    margin-right: .5rem;
  }

  &:hover {
    color: var(--fs-color-danger-100);
    background-color: var(--fs-color-danger-600);
  }
}

.errorfield--warning .errorfield__link {
  color: var(--fs-color-warning-800);
  background-color: var(--fs-color-warning-500);

  &:hover {
    color: var(--fs-color-warning-800);
    background-color: var(--fs-color-warning-600);
  }
}
</style>

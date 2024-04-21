<template>
  <div class="errorfield">
    <i
      class="errorfield__icon fas fa-exclamation-circle"
    />
    <div class="errorfield__content">
      <div class="errorfield__content-wrapper">
        <h4
          class="errorfield__title"
          v-text="$i18n(`error.${entry.field}.title`)"
        />
        <p
          class="errorfield__description"
          v-text="$i18n(`error.${entry.field}.description`, { link: entry.link })"
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
          :href="link.urlShortHand ? $url(link.urlShortHand) : link.href"
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
</style>

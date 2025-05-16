<template>
  <div>
    <notifications position="top left" style="max-width: 600px; width: unset;">
      <template #body="props">
        <div class="fs-notification-template fs-notification" :class="props.item.type">
          <div v-if="props.item.data?.icon" class="notification-prepend">
            <i :class="props.item.data.icon" />
          </div>
          <div class="notification-main">
            <div class="notification-title">
              {{ props.item.title }}
            </div>
            <div class="notification-content">
              <span v-text="props.item.text" />
              <small v-if="props.item.data?.details" class="notification-small">
                {{ props.item.data.details }}
              </small>
            </div>
          </div>
          <div class="notification-close">
            <b-btn
              variant="link"
              size="sm"
              @click="props.close"
            >
              <i class="fas fa-times" />
            </b-btn>
          </div>
        </div>
      </template>
    </notifications>
  </div>
</template>

<style lang="scss" scoped>
.fs-notification {
    // styling
    margin: 5px 5px;
    padding: 12px;
    color: var(--fs-color-primary-900);
    border-radius: 4px;
    display: grid;
    grid-template-areas:
"prepend content append close"
".       content .      .    ";
grid-template-columns: max-content auto max-content max-content;
    position: relative;

    background: var(--fs-color-primary-200);
    border-inline-start-width: 8px solid var(--fs-color-primary-300);
    // types (green, amber, red)
    &.info {
        background: var(--fs-color-info-200);
        border-left: 5px solid var(--fs-color-info-500);
    }

    &.success {
        background: var(--fs-color-success-300);
        border-left: 5px solid var(--fs-color-success-500);
    }

    &.warn {
        background: var(--fs-color-warning-300);
        border-left: 5px solid var(--fs-color-warning-500);
    }

    &.error {
        background: var(--fs-color-danger-300);
        border-left: 5px solid var(--fs-color-danger-500);
    }

    .notification-prepend {
      align-self: flex-start;
      display: flex;
      align-items: center;
      grid-area: prepend;
      margin-inline-end: 16px;

      .fas {
        font-size: 1.25rem; /* Smaller icon - was previously fa-2x (2rem) */
      }
    }

    .notification-main {
      align-self: center;
      display: flex;
      flex-direction: column;
      grid-area: content;
      overflow: hidden;
      .notification-content {
        white-space: preserve-breaks;
      }
    }

    .notification-title {
      align-items: center;
      font-weight: 600;
      margin-bottom: 5px;
      word-wrap: break-word;
      line-break: auto;
    }
    .notification-close {
      align-self: flex-start;
      grid-area: close;
      margin-inline-start: 16px;;
    }

    /* Hide icon on narrow screens */
    @media (max-width: 275px) {
      .notification-prepend {
        display: none;
      }

      grid-template-columns: 0 auto max-content max-content;
    }
  }
  </style>

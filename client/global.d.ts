import "vue";

declare module "vue/types/vue" {
  interface Vue {
    $url: typeof import('@/helper/urls').url;
    $dateFormatter: typeof import('@/helper/date-formatter').default;
    $isFeatureToggleActive: typeof import('@/helper/featuretoggles').isFeatureToggleActive;
  }
}

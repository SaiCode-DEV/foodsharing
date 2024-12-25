<template>
  <div class="partners">
    <div class="alert alert-secondary text-center">
      <h2>{{ i18n("partner_foodsharing") }}</h2>
    </div>
    <div v-for="(partnersList, category) in partners" :key="category">
      <div class="h5 category-head mb-2 mt-4">
        {{ category }}
      </div>
      <div class="partners-grid">
        <div
          v-for="partner in partnersList"
          :key="partner.name"
          class="partner"
        >
          <img
            :src="getLogoSrc(partner)"
            :alt="partner.name + ' Logo'"
            class="logo"
          >
          <h3 class="my-2 partner-name">
            {{ partner.name }}
          </h3>
          <p v-text="partner.description" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, defineProps } from 'vue'
import { getPartners } from './partners.js'
import { useThemeStore } from '@/stores/theme'
import { showLoader, hideLoader } from '@/script'
import i18n from '@/helper/i18n.js'

const props = defineProps({
  contentId: {
    type: Number,
    default: 10,
  },
})

const themeStore = useThemeStore()
const partners = ref({})

onMounted(async () => {
  showLoader()
  partners.value = await getPartners(props.contentId)
  hideLoader()
})

const getLogoSrc = (partner) => {
  return (themeStore.isDark && partner.logoDark) ? partner.logoDark : partner.logo
}
</script>

<style lang="scss" scoped>

.partners-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1rem;
  overflow: hidden;
}
@media (max-width: 450px) {
  .partners-grid {
    grid-template-columns: 1fr;
  }
}

.partner {
  break-inside: avoid;
  padding: 1rem;
  position: relative;
  border: 2px solid var(--fs-color-dark);
  border-radius: 10px;
  background-color: var(--fs-color-light);

  body.light-mode & {
    // for images with white background, make the upper part white
    background: linear-gradient(to bottom,#fff 40%, var(--fs-color-light) 100% );
  }

  .logo {
    margin: 0 auto;
    width: 100%;
    height: 9rem;
    object-fit: contain;
  }

  .partner-name {
    font-size: 1.3rem;
  }
}
</style>

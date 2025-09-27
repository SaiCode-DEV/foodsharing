<template>
  <div class="partners">
    <div class="alert alert-secondary text-center">
      <h2>{{ i18n("partner_foodsharing") }}</h2>
    </div>
    <div v-for="(partnersList, category) in partners" :key="category">
      <div :id="getCategoryId(category)" class="h5 category-head mb-2 mt-4">
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
            <a
              v-if="partner.link"
              :href="partner.link"
              target="_blank"
              rel="noopener"
            >
              {{ partner.name }}
            </a>
            <template v-else>
              {{ partner.name }}
            </template>
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

  await new Promise(resolve => {
    if (document.readyState === 'complete') resolve()
    else window.addEventListener('load', () => resolve(), { once: true })
  })

  const hash = window.location.hash
  if (hash) {
    const targetId = hash.substring(1)
    const element = document.getElementById(targetId)
    if (element) {
      // Calculate element position
      const elementPosition = element.getBoundingClientRect().top + window.pageYOffset
      window.scrollTo({
        top: elementPosition - 150, // Offset for navbar
        behavior: 'smooth',
      })
    }
  }
})

const getLogoSrc = (partner) => {
  return (themeStore.isDark && partner.logoDark) ? partner.logoDark : partner.logo
}

const getCategoryId = (category) => {
  // Remove colons and replace the rest with _
  return category.replace(/[:]/g, '').replace(/[^\w]/g, '_')
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

  a {
  color: inherit;
  text-decoration: none;

  &:hover {
    text-decoration: underline;
  }
}
}
</style>

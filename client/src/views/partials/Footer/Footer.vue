<template>
  <footer class="bg-white">
    <div class="container py-5">
      <b-row>
        <b-col
          sm="6"
          cols="12"
          class="h-100 mb-sm-0"
        >
          <h2>{{ $t(partnerData.title) }}</h2>
          <div class="d-flex flex-wrap" :class="{'with-info': hasPartnersWithInfo}">
            <a
              v-for="(item) in partnerData.items"
              :key="item.infosCompany"
              v-b-tooltip="$t(item.title)"
              class="partner hide-external"
              :class="{
                'alone': partnerData.items.length === 1,
              }"
              :href="$url(item.url)"
              :aria-label="$t(item.title)"
            >
              <img
                :alt="$t(item.title)"
                :src="themeStore.isDark && item.imgDark ? item.imgDark : item.img"
                loading="lazy"
              >
              <div class="h-0 position-relative">
                <p
                  v-if="item.info"
                  class="partner-info text-muted"
                  v-text="$t(item.info)"
                />
              </div>
            </a>
          </div>
        </b-col>
        <b-col
          sm="6"
          cols="12"
        >
          <h2>{{ $t('footer.donate.call_to') }}</h2>
          <a
            class="alert alert-secondary d-flex align-items-center mt-3"
            :href="$url('donations')"
          >
            <i class="icon icon--big fas fa-hands-helping mr-3" />
            {{ $t('footer.donate.cta') }}
          </a>
        </b-col>
      </b-row>
      <div class="line my-5" />
      <b-row>
        <b-col
          v-for="(data) in footerData"
          :key="data.title"
          md="4"
          cols="6"
          class="links"
        >
          <h2>{{ $t(data.title) }}</h2>
          <ul>
            <li
              v-for="(item) in data.items"
              :key="item.infosCompany"
              class="nav-item"
            >
              <a
                :href="$url(item.url)"
                :aria-label="$t(item.title)"
                v-text="$t(item.title)"
              />
            </li>
          </ul>
        </b-col>
      </b-row>
      <div class="line my-5" />
      <b-row class="justify-content-between">
        <b-col
          md="6"
          cols="12"
        >
          <b-row
            class="col"
          >
            <a
              :href="$url('imprint')"
              :aria-label="$t('footer.imprint')"
              class="mr-3"
            >
              {{ $t('footer.imprint') }}</a>
            <a
              :href="$url('dataprivacy')"
              :aria-label="$t('footer.dataprivacy')"
              class="mr-3"
            >
              {{ $t('footer.dataprivacy') }}
            </a>
            <a
              :href="$url('contact')"
              :aria-label="$t('menu.entry.contact')"
              class="mr-3"
            >
              {{ $t('menu.entry.contact') }}
            </a>
          </b-row>
          <b-row
            v-if="!isDotAt"
            class="col"
          >
            <a
              v-for="(social, index) in socialData"
              :key="index"
              v-b-tooltip="social.name"
              :href="$url(social.url + '_'+ (isDotAt ? 'at' : 'de'))"
              class="social_icons hide-external"
              :rel="externalLink"
            >
              <!-- This is a workaround for the bluesky icon and can be removed it is added to fontawesome -->
              <img
                v-if="social.icon.startsWith('/')"
                :src="themeStore.isDark && social.iconDark ? social.iconDark : social.icon"
                width="19px"
                height="19px"
                style="vertical-align: middle"
              >
              <i v-else :class="social.icon" />
              <span class="sr-only" v-text="social.name" />
            </a>
          </b-row>
        </b-col>
        <b-col
          md="6"
          cols="12"
          class="d-flex flex-column align-items-md-end"
        >
          <a
            :href="$url('release_notes')"
            v-text="$t('releases.2025-08')"
          />
          <span> {{ $t('footer.meta.made_with') }}
            <i class="made-with-love-icon fas fa-heart" />
            <a :href="$url('devdocs')" v-text="$t('footer.meta.it_devdocs')" />
          </span>

          <a
            v-if="version && isBeta"
            class="text-truncate"
            :href="$url('git_revision', version)"
            v-text="$t('footer.meta.version', { version })"
          />
        </b-col>
      </b-row>
    </div>
  </footer>
</template>

<script>
// Data
import SocialData from './Data/SocialData.json'
import FooterData from './Data/FooterData.json'
import PartnerData from './Data/PartnerData.json'
// Mixins
import RouteCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'
import serverData from '@/helper/server-data'
// Theme
import { useThemeStore } from '@/stores/theme'

export default {
  mixins: [RouteCheckMixin],
  setup () {
    const themeStore = useThemeStore()
    return { themeStore }
  },
  data () {
    return {
      externalLink: 'nofollow noreferrer noopener',
      socialData: SocialData,
      footerData: FooterData,
      version: serverData.version,
    }
  },
  computed: {
    partnerData () {
      return this.isDotAt ? PartnerData.at : PartnerData.de
    },
    hasPartnersWithInfo () {
      return this.partnerData.items.some(item => item.info)
    },
  },
}
</script>

<style lang="scss" scoped>
.social_icons {
  color: var(--fs-color-secondary-900);
  font-size: 1.2rem;
  padding: .25rem;
  transition: color .2s ease-in-out;

  :not(:last-child) {
    margin-right: .5rem;
  }

  &:hover {
    color: var(--fs-color-secondary-600);
  }
}

.partner {
  margin: .75rem;
  display: inline-block;
  min-width: 75px;

  img {
    height: 100%;
    object-fit: contain;
  }
  &.alone {
    img {
      max-width: 156px;
    }
  }
  &:not(.alone) {
    img {
      max-width: 75px;
      @media (max-width: 768px) {
        max-width:  45px;
      }
    }
  }

  &:first-child {
    margin-left: 0;
  }

  .partner-info {
    position: absolute;
    top: 0.5rem;
    line-height: 1.2em;
  }
}

.with-info {
  position: relative;
  padding-bottom: 2rem;
}

ul {
  list-style-type: none;
  margin-left: 0;
}

h2 {
  font-size: 1.1rem;
}

.line {
  border-bottom: 1px solid var(--fs-border-default);
}

footer {
  a, p, li {
    font-size: .8rem;
    line-height: 1.8em;
    color: var(--fs-color-dark);
    text-decoration: none;
    font-weight: normal;
  }

  .made-with-love-icon {
    color: var(--fs-color-danger-600);
  }

  .alert {
      color: var(--fs-color-secondary-600);
      font-size: 0.9rem;
      font-weight: 600;
      transition: all .2s ease-in-out;
      line-height: 1.35;

      &:hover {
        background-color: var(--fs-color-secondary-600);
        color: var(--fs-color-secondary-100);
      }
  }
}
</style>

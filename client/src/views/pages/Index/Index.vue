<template>
  <b-container
    fluid
    class="bg-white p-0"
  >
    <div class="card text-white mb-5">
      <div class="header-desktop d-flex align-items-center">
        <b-container>
          <b-row class="d-flex align-content-center justify-content-center">
            <b-col
              lg="2"
              class="d-none d-lg-block"
            >
              <Fork />
            </b-col>
            <b-col
              xl="8"
              lg="10"
              cols="12"
              class="text-center mt-5"
            >
              <b-card-title class="mt-4">
                <h1 class="text-dark">
                  {{ $i18n('startpage.welcome_text') }}
                </h1>
              </b-card-title>
              <b-card-text class="mt-4">
                <h3 class="text-dark">
                  {{ $i18n('startpage.slogan_1') }}
                </h3>
              </b-card-text>
              <b-card-text class="mt-5 mb-5 text-center">
                <b-button
                  variant="primary"
                  class="pl-5 pr-5"
                  :href="$url('joininfo')"
                >
                  {{ $i18n('startpage.call_to_action_button') }}
                </b-button>
              </b-card-text>
              <h6
                class="text-dark d-none d-sm-block mb-sm-5"
              >
                <b>{{ $i18n('startpage.slogan_2') }}</b><br>
                {{ $i18n('startpage.slogan_3') }}
              </h6>
            </b-col>
          </b-row>
        </b-container>
      </div>
    </div>
    <b-row
      v-if="contentBlockVisible"
      class="container m-auto"
    >
      <b-col
        cols="12"
        md="12"
        xl="3"
        class="text-xl-right"
      >
        <!-- eslint-disable vue/no-v-html -->
        <!-- Sanitized in src/Modules/Content/ContentGateway.php getMultiple() -->
        <span v-html="contentBlock1" />
        <!-- eslint-enable -->
      </b-col>
      <b-col
        cols="12"
        xl="5"
        md="6"
      >
        <!-- eslint-disable vue/no-v-html -->
        <!-- Sanitized in src/Modules/Content/ContentGateway.php getMultiple() -->
        <span v-html="contentBlock2" />
        <!-- eslint-enable -->
      </b-col>
      <b-col
        cols="12"
        md="6"
        xl="4"
      >
        <!-- eslint-disable vue/no-v-html -->
        <!-- Sanitized in src/Modules/Content/ContentGateway.php getMultiple() -->
        <span v-html="contentBlock3" />
        <!-- eslint-enable -->
      </b-col>
    </b-row>
    <div
      v-if="contentBlockVisible"
      class="line pt-3 pt-xl-5"
    />
    <b-row class="container mt-5 ml-auto mr-auto">
      <b-col
        cols="12"
        xl="3"
        class="text-xl-right"
      >
        <h2>{{ $i18n('startpage.vision.title') }}</h2>
      </b-col>
      <b-col
        cols="12"
        xl="6"
      >
        <p>
          {{ $i18n('startpage.vision.text') }}
        </p>
        <p>
          <b-link
            class="link"
            href="ueber-uns"
          >
            {{ $i18n('startpage.more_infos') }}
          </b-link>
        </p>
      </b-col>
    </b-row>
    <b-row class="container mt-5 ml-auto mr-auto">
      <b-col
        xl="4"
        class="text-xl-right"
      >
        <h2>{{ $i18n('startpage.this_is_how_it_works') }}</h2>
      </b-col>
      <b-col
        cols="12"
      >
        <b-embed
          type="video"
          aspect="16by9"
          controls
          allowfullscreen
          poster="/img/startpage/Video-1200.png"
          loading="lazy"
        >
          <source
            src="/img/startpage/intro.webm"
            type="video/webm"
          >
          <source
            src="/img/startpage/intro.mp4"
            type="video/mp4"
          >
        </b-embed>
      </b-col>
    </b-row>
    <b-row class="container mt-5 ml-auto mr-auto">
      <b-col
        cols="12"
        xl="4"
        class="text-xl-right"
      >
        <h2>{{ $i18n('startpage.call_to_action.title') }}</h2>
      </b-col>
      <b-col
        cols="12"
        xl="4"
        class="ml-xl-5"
      >
        <p>
          {{ $i18n('startpage.call_to_action.text') }}
        </p>
      </b-col>
    </b-row>
    <div class="card text-white mb-5 mt-5">
      <div class="call-to-action-image">
        <b-row class="container ml-auto mr-auto pb-5 pt-5 row">
          <b-card
            bg-variant="bg-white"
            text-variant="dark"
            class="p-4"
          >
            <b-card-text>
              <h5>{{ $i18n('startpage.member.title') }}</h5>
            </b-card-text>
            <b-card-text>
              {{ $i18n('startpage.member.text') }}
              <b-link
                class="link"
                href="/content?sub=joininfo"
              >
                {{ $i18n('startpage.more_infos') }}
              </b-link>
            </b-card-text>
          </b-card>
          <b-card
            bg-variant="bg-white"
            text-variant="dark"
            class="p-4"
          >
            <b-card-text>
              <h5>{{ $i18n('startpage.private.title') }}</h5>
            </b-card-text>
            <b-card-text>
              {{ $i18n('startpage.private.text') }}
            </b-card-text>
          </b-card>
          <b-card
            bg-variant="bg-white"
            text-variant="dark"
            class="p-4"
          >
            <b-card-text>
              <h5>{{ $i18n('startpage.store.title') }}</h5>
            </b-card-text>
            <b-card-text>
              {{ $i18n('startpage.store.text') }}
              <b-link
                class="link"
                href="fuer-unternehmen"
              >
                {{ $i18n('startpage.more_infos') }}
              </b-link>
            </b-card-text>
          </b-card>
        </b-row>
      </div>
    </div>
    <b-row class="container mt-5 ml-auto mr-auto">
      <b-col
        cols="12"
        xl="3"
        class="text-xl-right"
      >
        <h2>{{ $i18n('startpage.how_we_work.title') }}</h2>
      </b-col>
      <b-col
        cols="12"
        xl="6"
      >
        <p>
          {{ $i18n('startpage.how_we_work.text') }}
        </p>
        <p>
          <b-link
            class="link"
            :href="$url('devdocs')"
          >
            {{ $i18n('startpage.more_infos') }}
          </b-link>
        </p>
      </b-col>
    </b-row>
    <div class="card text-white pb-5 mt-5">
      <div class="teaser-map p-5">
        <b-row
          align-h="end"
          class="container mt-5 ml-auto mr-auto"
        >
          <b-col
            xl="6"
            cols="12"
          >
            <b-card-group
              deck
            >
              <b-card
                bg-variant="bg-white"
                text-variant="dark"
                class="p-4"
              >
                <b-card-text>
                  <h5>{{ $i18n('startpage.near_you.title') }}</h5>
                </b-card-text>
                <b-row>
                  <b-col
                    cols="12"
                    xl="6"
                    class="pl-0 mb-3"
                  >
                    <b-card-text>
                      {{ $i18n('startpage.near_you.text1.part1') }} <b-link
                        class="link"
                        :href="$url('map')"
                      >
                        {{ $i18n('startpage.near_you.text1.part2') }}
                      </b-link>
                      {{ $i18n('startpage.near_you.text1.part3') }}
                      <b>{{ $i18n('startpage.near_you.text1.part4') }}</b>{{ $i18n('startpage.near_you.text1.part5') }}
                    </b-card-text>
                  </b-col>
                  <b-col
                    cols="12"
                    xl="6"
                    class="pl-0"
                  >
                    <b-card-text>
                      {{ $i18n('startpage.near_you.text2.part1') }} <b>{{ $i18n('startpage.near_you.text2.part2') }}</b>
                      {{ $i18n('startpage.near_you.text2.part3') }}
                    </b-card-text>
                  </b-col>
                </b-row>
                <b-card-text class="pt-4">
                  {{ $i18n('startpage.near_you.text3.part1') }} <b-link
                    class="link"
                    :href="$url('communities')"
                  >
                    {{ $i18n('startpage.near_you.text3.part2') }}
                  </b-link>
                </b-card-text>
              </b-card>
            </b-card-group>
          </b-col>
        </b-row>
      </div>
      <div class="map-copyright">
        <span v-html="MapAttribution" />
        <span class="ml-2">
          Image by
          <a href="https://de.freepik.com/freie-psd/laptop-mockup-design_11653801.htm">
            Freepik, xvector
          </a>
        </span>
      </div>
    </div>
    <b-row
      class="container mt-5 ml-auto mr-auto"
      align-v="center"
    >
      <b-col
        cols="12"
        md="4"
      >
        <b-row class="mt-2 mt-md-0">
          <b-col><h2>{{ $i18n('startpage.demands.title') }}</h2></b-col>
        </b-row>
        <b-row class="mt-2">
          <b-col>
            {{ $i18n('startpage.demands.text') }}
            <b-link
              class="link"
              href="/content?sub=forderungen"
            >
              {{ $i18n('startpage.more_infos') }}
            </b-link>
          </b-col>
        </b-row>
      </b-col>
      <b-col
        cols="12"
        md="8"
        class="mt-3 align-self-end"
      >
        <b-img-lazy
          fluid
          src="/img/startpage/picture-forderungen.png"
        />
      </b-col>
    </b-row>
    <b-row
      class="container mt-5 ml-auto mr-auto"
      align-v="center"
    >
      <b-col
        cols="12"
        md="6"
        class="mb-3"
      >
        <img
          class="card-img teaser-towns"
          src="/img/startpage/foodsharing-staedte.png"
          alt="Card image"
          loading="lazy"
          fluid
        >
      </b-col>
      <b-col
        cols="12"
        md="6"
      >
        <h2>{{ $i18n('startpage.towns.title') }}</h2>
        <br>
        <p>
          {{ $i18n('startpage.towns.text') }}
          <b-link
            class="link"
            href="https://foodsharing-staedte.org"
          >
            {{ $i18n('startpage.more_infos') }}
          </b-link>
        </p>
      </b-col>
    </b-row>
    <div class="card text-white mb-5 mt-5">
      <div class="teaser-donate p-5">
        <b-row
          class="container ml-auto mr-auto"
        >
          <b-col
            xl="6"
            cols="12"
          >
            <b-card-group
              deck
            >
              <b-card
                bg-variant="bg-white"
                text-variant="dark"
                class="p-4"
              >
                <b-row class="mb-3">
                  <b-col
                    cols="10"
                    md="11"
                    class="pl-0"
                  >
                    <h5>{{ $i18n('startpage.donate.title') }}</h5>
                  </b-col>
                </b-row>
                <b-card-text>
                  {{ $i18n('startpage.donate.text') }}
                  <a class="link" href="unterstuetzung">
                    {{ $i18n('startpage.donate.link') }}
                  </a>
                </b-card-text>
              </b-card>
            </b-card-group>
          </b-col>
        </b-row>
      </div>
    </div>
  </b-container>
</template>

<script>
import Fork from './fork'
import { MAP_ATTRIBUTION } from '@/consts'

export default {
  components: {
    Fork,
  },
  props: {
    contentBlock1: { type: String, default: '' },
    contentBlock2: { type: String, default: '' },
    contentBlock3: { type: String, default: '' },
  },
  computed: {
    contentBlockVisible () {
      return this.contentBlock1 && this.contentBlock2 && this.contentBlock1
    },
    MapAttribution () {
      return MAP_ATTRIBUTION
    },
  },
}

</script>

<style lang="scss" scoped>
.row {
  margin-left: 0px;
  margin-right: 0px;
}

@media screen and (max-width: 767px) {
      h3 {
        font-size: 1rem;
      }
  }

h5 {
  font-family: var(--fs-font-family-headline);
}

.line {
  border-bottom: 1px solid var(--fs-border-default);
  width: 90%;
  margin: 0 auto;
}

svg {
  height: auto;
  width: 14em;
}

.embed-responsive {
  max-width: 80em;
  margin: 0 auto;
}

.teaser-towns {
  border-radius: 0px !important;
}

.teaser-background {
  background-color: var(--fs-color-primary-200);
  max-width: 805px;
  max-height: 451px;
}

.call-to-action-image {
  background-image: url(/img/startpage/mach-mit.png);
  background-size: cover;

  & .container {
    flex-wrap: wrap;

    .card {
      flex: 1;
      margin: 0.5rem;
    }

    @media screen and (max-width: 767px) {
      flex-wrap: unset;
      flex-direction: column;
    }
  }
}

.header-desktop {
  background-image: url(/img/startpage/header.png);
  background-size: cover;
}

.teaser-map {
  background-image: url(/img/startpage/karte.jpg);
  background-size: cover;
  min-height: 40rem;
}

.teaser-donate {
  background-image: url(/img/startpage/spenden.png);
  background-size: cover;
}

.map-copyright {
  color: var(--fs-color-dark);
  text-align: center;
  z-index: 1;
}
</style>

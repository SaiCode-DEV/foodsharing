<template>
  <Container :title="$i18n('startpage.join_rules')" :collapsible="false">
    <div class="list-group-item">
      <h3 class="mb-3">
        {{ $i18n('startpage.join_welcome') }}
      </h3>
      <Markdown :source="$i18n('startpage.respect')" class="lead" />
      <p><strong>{{ $i18n('startpage.register') }}</strong></p>
      <h3 class="mb-3">
        {{ $i18n('startpage.forstores') }}
      </h3>
      <Markdown :source="$i18n('startpage.together')" />

      <div class="card border my-4">
        <div class="card-body">
          <h2 class="card-title mb-4">
            {{ $i18n('startpage.etiquette') }}
          </h2>
          <div
            v-for="(item, index) in etiquetteItems"
            :key="index"
            class="mb-3 d-flex"
          >
            <b-badge
              variant="danger"
              class="mr-3 rounded-circle d-flex align-items-center justify-content-center badge-custom"
            >
              {{ index + 1 }}
            </b-badge>
            <div>
              <h3>{{ $i18n(item.title) }}</h3>
              <Markdown v-if="item.description" :source="$i18n(item.description)" />
              <Markdown
                v-if="item.additionalInfo"
                :source="$i18n(item.additionalInfo)"
                class="pt-2"
              />
            </div>
          </div>
        </div>
      </div>
      <div class="text-center mt-4">
        <b-button
          :href="$url('register')"
          variant="primary"
        >
          {{ $i18n('startpage.registernow') }}
        </b-button>
      </div>
    </div>
  </Container>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown.vue'
import Container from '@/components/Container/Container.vue'

export default {
  name: 'JoinInfo',
  components: {
    Markdown,
    Container,
  },
  data () {
    return {
      etiquetteItems: [
        {
          title: 'startpage.honest',
          description: 'startpage.telltruth',
        },
        {
          title: 'startpage.followrules_title',
          description: 'startpage.followrules',
          additionalInfo: 'startpage.notallowed',
        },
        {
          title: 'startpage.beresponsible',
          description: 'startpage.responsibility',
        },
        {
          title: 'startpage.bedependable',
          description: 'startpage.dependability',
        },
        {
          title: 'startpage.makeproposals',
          description: 'startpage.proposals',
        },
      ],
    }
  },
}
</script>

<style scoped>
.badge-custom {
  height: 1.8rem;
  font-size: 1.2rem;
  min-width: 1.8rem;
}
</style>

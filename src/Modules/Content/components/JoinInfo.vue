<template>
  <Container :title="$t('startpage.join_rules')" :collapsible="false">
    <div class="list-group-item">
      <h3 class="mb-3">
        {{ $t('startpage.join_welcome') }}
      </h3>
      <Markdown :source="$t('startpage.respect')" class="lead" />
      <p><strong>{{ $t('startpage.register') }}</strong></p>
      <h3 class="mb-3">
        {{ $t('startpage.forstores') }}
      </h3>
      <Markdown :source="$t('startpage.together')" />

      <div class="card border my-4">
        <div class="card-body">
          <h2 class="card-title mb-4">
            {{ $t('startpage.etiquette') }}
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
              <h3>{{ $t(item.title) }}</h3>
              <Markdown v-if="item.description" :source="$t(item.description)" />
              <Markdown
                v-if="item.additionalInfo"
                :source="$t(item.additionalInfo)"
                class="pt-2"
              />
            </div>
          </div>
        </div>
      </div>
      <div class="text-center mt-4">
        <b-button
          :to="$url('register')"
          variant="primary"
        >
          {{ $t('startpage.registernow') }}
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

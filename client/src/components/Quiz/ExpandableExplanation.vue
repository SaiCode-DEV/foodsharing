<template>
  <div>
    <span
      ref="explanation"
      class="explanation"
    >
      <b>{{ $t('explanation') }}:</b>
      {{ text }}

    </span>
    <a
      href="#expand"
      @click="expand"
    >
      {{ $t('globals.show_more') }}
    </a>
  </div>
</template>

<script>
export default {
  props: {
    text: { type: String, required: true },
  },
  methods: {
    expand () {
      this.$refs.explanation.classList.add('expanded')
    },
  },
}
</script>

<style lang="scss" scoped>

#comment-collapse {
  text-align: right;
  .btn{
    margin-top: .5em;
  }
}
.show-full-expl {
  position: relative;
  top: -5px;
}
.explanation:not(.expanded) {
  overflow: hidden;
  -webkit-box-orient: vertical;
  display: -webkit-inline-box;
  max-width: calc(100% - 10em);
  -webkit-line-clamp: 1;
  vertical-align: bottom;
  word-break: break-all;
}
.explanation.expanded ~ a {
  display: none;
}
</style>

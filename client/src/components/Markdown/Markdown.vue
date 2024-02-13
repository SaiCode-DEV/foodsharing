<script>
import markdown from './markdownRenderer'
export default {
  functional: true,
  props: {
    source: { type: String, required: true },
    classes: { type: String, default: '' },
  },
  render (h, { props, data }) {
    if (data.style || data.class || data.staticClass) {
      throw new Error('Markdown component does not support style or class attributes')
    }
    return h('div', {
      class: 'markdown ' + props.classes,
      domProps: {
        innerHTML: markdown.render(props.source),
      },
    })
  },
}
</script>

<style lang="scss">
.markdown {
  p:last-child {
    margin-bottom: 0;
  }
  a {
    word-break: break-word;
  }
  code {
    word-break: break-all;
  }
  img {
    width: 100%;
    max-width: fit-content;
    border: 1px solid var(--fs-border-default);
    border-radius: var(--border-radius);
  }
}
</style>

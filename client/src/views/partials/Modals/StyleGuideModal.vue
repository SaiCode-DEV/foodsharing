<template>
  <b-modal
    id="styleGuideModal"
    ref="styleGuideModal"
    size="xl"
    title="Styleguide"
  >
    <div class="flex">
      <div class="mb-5">
        <h2>Colors</h2>
        <hr>
        <div class="flex">
          <div
            v-for="(color, idx) in cssColorRules"
            :key="idx"
            class="col col-6"
          >
            <small
              v-text="color[0].type.name.toUpperCase()"
            />
            <div class="flex">
              <div
                v-for="({type, rule, base, value}) in color"
                :key="rule"
                v-b-tooltip="value"
                class="box"
                :class="{ 'base': base }"
                :style="`background-color: var(${rule})`"
                :data-content="type.step"
                @click="copyToClipboard(rule)"
              />
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6">
        <div class="mb-5">
          <div class="mb-5">
            <h2>
              Fonts
            </h2>
            <hr>
            <ul>
              <li>
                <strong>Headlines:</strong>
                <a
                  href="https://fonts.google.com/specimen/Alfa+Slab+One"
                  v-text="'Alfa Slab One'"
                />
              </li>
              <li>
                <strong>Body:</strong>
                <a
                  href="https://fonts.google.com/specimen/Poppins"
                  v-text="'poppins'"
                />
              </li>
            </ul>
          </div>
          <h2>
            Icons
          </h2>
          <hr>
          <ul>
            <li>
              <a
                href="https://fontawesome.com/v5/search?m=free&s=solid%2Cbrands"
                v-text="'Font Awesome V5 [FREE]'"
              />
            </li>
            <li>
              <a
                href="https://fontawesome.com/v5/docs/web/style/animate#contentHeader"
                v-text="'Animated Icons'"
              />
            </li>
          </ul>
        </div>
        <div class="mb-5">
          <h2>
            Components
          </h2>
          <hr>
          <ul>
            <li>
              <a
                href="https://getbootstrap.com/docs/4.6/getting-started/introduction/"
                v-text="'Bootstrap 4.6'"
              />
            </li>
            <li>
              <a
                href="https://www.bootstrap-vue.org/"
                v-text="'Bootstrap-VUE 2.18.1'"
              />
            </li>
          </ul>
          <div class="alert alert-info">
            Please only use <b>Bootstrap-VUE</b>, when using vanilla Bootstrap interactiv comoponents like you will break old components which uses jQuery UI.
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6">
        <div class="mb-5">
          <h2>
            Font Preview
          </h2>
          <hr>
          <!-- eslint-disable vue/no-v-html -->
          <!-- Html is generated in the javascript function below -->
          <div v-html="generateFontRules()" />
          <!-- eslint-enable -->
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script>
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin'

export default {
  name: 'StyleGuideModal',
  mixins: [CopyToClipboardMixin],
  data () {
    return {
      bootstrapMainRules: ['primary', 'light', 'secondary', 'danger', 'warning', 'info'],
      bootstrapButtonStyles: ['btn btn-', 'btn btn-outline-', 'btn btn-sm btn-', 'btn btn-sm btn-outline-'],
      cssColorRules: [],
      cssFontRules: [
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'p',
        'span',
        'a',
        'b',
        'strong',
        'i',
        'em',
        'small',
        'code',
        'mark',
        'ins',
        'del',
        'sup',
        'sub',
        'blockquote',
        'pre',
        'ul',
        'ol',
        'dl',
      ],
    }
  },
  created () {
    const root = document.querySelector(':root')
    const cssColorRules = Array.from(document.styleSheets)
      .filter(sheet => sheet.href === null || sheet.href.startsWith(window.location.origin))
      .reduce((acc, sheet) => (acc = [...acc, ...Array.from(sheet.cssRules)]), [])
      .filter(rule => rule.selectorText === ':root')
      .reduce((acc, sheet) => (acc = [...acc, ...Array.from(sheet.style)]), [])
      .filter(name => name.startsWith('--') && name.includes('color'))
      .map((rule) => ({ type: this.extractColor(rule), rule, value: getComputedStyle(root).getPropertyValue(rule) }))
    this.cssColorRules = this.chunkArray(cssColorRules, 9)
  },
  methods: {
    generateFontRules () {
      return this.cssFontRules
        .map((font) => {
          const str = []
          str.push('<div>')
          if (font === 'a') {
            str.push(`<${font} href="#">`)
          } else {
            str.push(`<${font}>`)
          }

          if (['ul', 'ol'].includes(font)) {
            str.push(`<li>(${font}) li, The quick brown fox jumps over ...</li>`)
            str.push(`<li>(${font}) li, The quick brown fox jumps over ...</li>`)
            str.push(`<li>(${font}) li, The quick brown fox jumps over ...</li>`)
            str.push(`<li>(${font}) li, The quick brown fox jumps over ...</li>`)
          } else if (font === 'dl') {
            str.push(`<dt>(${font}) dt, The quick brown fox jumps over ...</dt>`)
            str.push(`<dd>(${font}) dd, The quick brown fox jumps over ...</dd>`)
            str.push(`<dt>(${font}) dt, The quick brown fox jumps over ...</dd>`)
            str.push(`<dd>(${font}) dd, The quick brown fox jumps over ...</dd>`)
          } else {
            str.push(`(${font}), The quick brown fox jumps over ...`)
          }
          str.push(`</${font}>`)
          str.push('</div>')
          return str.join('')
        })
        .join('')
    },
    extractColor (color) {
      const regex = /--fs-color-(.+)-(\d+)/gm
      const match = regex.exec(color) || ['', 'others', color]
      return match ? { name: match[1], step: match[2] } : 'OTHERS'
    },
    chunkArray (myArray, chunkSize) {
      const arrayLength = myArray.length
      const tempArray = []
      for (let index = 0; index < arrayLength; index += chunkSize) {
        tempArray.push(myArray.slice(index, index + chunkSize))
      }
      return tempArray
    },
  },
}
</script>
<style lang="scss" scoped>
.box {
  width: calc(calc(100% / 3) - 8px);
  height: 1.25rem;
  border-radius: var(--border-radius);
  margin: 4px;
  cursor: copy;
  display: flex;
  align-items: center;
  justify-content: center;

  &:after {
    content: attr(data-content);
    mix-blend-mode: difference;
    color: white;
    font-size: 0.7rem;
    @media (max-width: 576px) {
      display: none;
    }
  }

  &:hover {
    outline: 2px solid var(--fs-color-dark);
  }
}

.col:not(:last-child) {
  padding-right: 0;
}

.flex {
  display: flex;
  flex-flow: wrap;

  &:not(:last-child) {
    margin-bottom: 1rem;
    margin-right: 1rem;
  }
}
</style>

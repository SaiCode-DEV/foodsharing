<script>
import { BFormDatepicker } from 'bootstrap-vue'
import i18n from '@/helper/i18n'

const translations = ['locale', 'placeholder', 'labelPrevYear', 'labelPrevYear', 'labelPrevMonth', 'labelCurrentMonth', 'labelNextMonth', 'labelNextYear', 'labelToday', 'labelSelected', 'labelNoDateSelected', 'labelCalendar', 'labelNav', 'labelHelp', 'labelResetButton']

export default {
  extends: BFormDatepicker,
  props: {
    ...BFormDatepicker.props,
    ...Object.fromEntries(translations.map(key => [key, {
      type: String,
      default: i18n(`calendar.${key}`),
    }])),
    hideHeader: { type: Boolean, default: true },
    startWeekday: { type: Number, default: 1 },
    short: { type: Boolean, default: true },
    dateFormatOptions: {
      type: Object,
      default () {
        const short = this.$options.propsData.short
        if (short === '' || Boolean(short)) {
          return { weekday: 'short', month: '2-digit' }
        }
        return { weekday: 'long', month: 'long' }
      },
    },
    valueAsDate: { type: Boolean, default: true },
  },
}
</script>

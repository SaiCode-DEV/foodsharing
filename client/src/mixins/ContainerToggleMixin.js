export default {
  data () {
    return {
      list: [],
      defaultFallbackAmount: 5,
      defaultAmountForMobileMixin: null,
      defaultAmountForDesktopMixin: null,
      amount: this.defaultAmount,
      isReduced: true,
    }
  },
  computed: {
    filteredList () {
      return this.list?.slice(0, this.amount)
    },
    isMobile () {
      return this.wXS || this.wSM || this.wMD
    },
    defaultAmount () {
      const amountForMobile = this.defaultAmountForMobileMixin || this.defaultFallbackAmount
      const amountForDesktop = this.defaultAmountForDesktopMixin || this.defaultFallbackAmount
      return this.isMobile ? amountForMobile : amountForDesktop
    },
  },
  methods: {
    setDefaultAmountForDesktop (newValue) {
      this.defaultAmountForDesktopMixin = newValue
    },

    setDefaultAmountForMobile (newValue) {
      this.defaultAmountForMobileMixin = newValue
    },
    showFullList () {
      this.isReduced = false
      this.amount = this.list?.length
    },
    reduceList () {
      this.isReduced = true
      this.amount = this.defaultAmount
    },
    setList (list) {
      this.list = list
      if (this.isReduced) {
        this.reduceList()
      } else {
        this.showFullList()
      }
    },
  },
}

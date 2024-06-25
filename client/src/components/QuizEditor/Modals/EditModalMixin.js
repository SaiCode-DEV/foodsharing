export default {
  data: () => ({
    form: {},
  }),
  computed: {
    hasValidValues () {
      return Object.values(this.validities).every(x => x)
    },
  },
  methods: {
    initializeFormData (...sources) {
      this.form = Object.assign({}, ...sources)
    },
  },
}

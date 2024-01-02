import Storage from '@/storage'
const storage = new Storage()

export default {
  data () {
    return {
      state: false,
      stateHasAutoClose: false,
      stateHasAutoSave: false,
      stateTag: 'StatTogglerMixin-missing-override',
    }
  },
  mounted () {
    if (this.stateHasAutoSave) {
      this.loadState()
    }
    document.addEventListener('click', this.close)
  },
  beforeDestroy () {
    document.removeEventListener('click', this.close)
  },
  methods: {
    loadState () {
      this.state = storage.get(this.stateTag)
    },
    saveState () {
      storage.set(this.stateTag, this.state)
    },
    toggleState () {
      this.state = !this.state
      if (this.stateHasAutoSave) {
        this.saveState()
      }
    },
    close (e) {
      if (this.stateHasAutoClose && !this.$el.contains(e.target)) {
        this.state = false
      }
    },
  },
}

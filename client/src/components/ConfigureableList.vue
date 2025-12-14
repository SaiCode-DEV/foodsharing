<template>
  <div>
    <slot
      name="head"
      :show-configuration-dialog="showConfigurationDialog"
    >
      <h4>Use the named slot "head" to control this content.</h4>
      <button
        type="button"
        @click="$refs['configure-modal'].show()"
      >
        configure
      </button>
    </slot>
    <slot v-if="selection.length" />
    <b-modal
      ref="configure-modal"
      modal-class="bootstrap"
      floating
      size="lg"
      hide-header-close
      @ok="save"
      @close="reset"
      @hide="handleCloseOnEscOrBackdrop"
    >
      <template #modal-header="{ close }">
        <h4>{{ $t('configure_columns') }}</h4>
        <button
          type="button"
          class="btn btn-sm no-shadow"
          @click="close"
        >
          <i class="fas fa-xmark" />
        </button>
      </template>
      <template #default>
        <DragAndDropSortList v-model="componentFields" class="mb-1">
          <template #item="{ item, events }">
            <div class="d-flex align-items-center checkbox-nest">
              <input
                :id="`${storageKey}-${item[fieldKey]}`"
                v-model="componentSelection"
                type="checkbox"
                :value="item[fieldKey]"
              >
            </div>
            <div class="d-flex align-items-center">
              <label
                :for="`${storageKey}-${item[fieldKey]}`"
                class="mb-0 ml-1"
              >
                {{ item[fieldLabel] }}
              </label>
            </div>
            <button
              type="button"
              class="btn btn-sm ml-auto shadow-none"
              draggable="true"
              v-on="events"
            >
              <i class="fas fa-bars" />
            </button>
          </template>
        </DragAndDropSortList>
        <div v-if="state">
          <hr>
          <div class="form-group form-check mb-0">
            <input
              id="save-filter-checkbox"
              v-model="saveStateInternal"
              type="checkbox"
              class="form-check-input"
            >
            <label
              v-b-tooltip.hover="$t('save_state_description')"
              class="form-check-label"
              for="save-filter-checkbox"
            >{{ $t('save_state') }}</label>
          </div>
        </div>
      </template>
      <template #modal-footer="{ ok }">
        <b-button
          class="col"
          @click="resetDefaults"
        >
          {{ $t('button.reset_default') }}
        </b-button>
        <b-button
          variant="primary"
          @click="ok"
        >
          {{ $t('button.save') }}
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script>
import DragAndDropSortList from '@/components/DragAndDropSortList.vue'
import Storage from '@/storage'
import { arrayContentEquals, arrayEquals, debounce } from '@/utils'

export default {
  components: { DragAndDropSortList },
  props: {
    fields: {
      type: Array,
      required: true,
    },
    selection: {
      type: Array,
      required: true,
    },
    fieldKey: {
      type: String,
      default: 'key',
    },
    fieldLabel: {
      type: String,
      default: 'label',
    },
    storageKey: {
      type: String,
      default: function () { return this.$parent.$options._componentTag },
    },
    saveState: {
      type: Boolean,
      default: true,
    },
    defaultSelection: {
      type: Array,
      default: function () { return this.selection },
    },
    defaultFields: {
      type: Array,
      default: function () { return this.fields.map(field => field[this.fieldKey]) },
    },
    state: {
      type: Object,
      default: null,
    },
  },
  data () {
    return {
      saveStateInternal: this.saveState,
      dataLoaded: false,
      initialSelection: [],
      initialFields: [],
      initialSavestate: this.saveState,
    }
  },
  computed: {
    componentSelection: {
      get () {
        return this.selection
      },
      set (value) {
        this.$emit('update:selection', value)
      },
    },
    componentFields: {
      get () {
        return this.fields
      },
      set (fieldsOrFieldKeys) {
        const value = typeof fieldsOrFieldKeys[0] === 'string' ? fieldsOrFieldKeys : fieldsOrFieldKeys.map(field => field[this.fieldKey])
        this.$emit('update:fields', value)
      },
    },
    fieldsOrder () {
      return this.fields.map(field => field[this.fieldKey])
    },
    unsavedChanges () {
      return !arrayEquals(this.initialFields, this.fieldsOrder) || !arrayContentEquals(this.initialSelection, this.componentSelection) || this.initialSavestate !== this.saveStateInternal
    },
  },
  watch: {
    state: {
      handler: function (state) {
        this.debouncedSaveState(state)
      },
      deep: true,
    },
    defaultFields: {
      handler: function (value, oldValue) {
        // has default field configuration
        if (arrayEquals(this.initialFields, oldValue)) {
          this.initialFields = value
          this.componentFields = value
        }
      },
    },
  },
  created () {
    this.storage = new Storage(`vue-${this.storageKey}`)
    this.stateStorage = new Storage(`vue-${location.pathname}${location.search}`)
    this.debouncedSaveState = debounce(state => {
      if (this.saveStateInternal) {
        this.stateStorage.set('state', state)
      }
    }, 500)
    this.setInitialData()
    this.load()
    window.addEventListener('beforeunload', this.unsavedChangesPrompt)
  },
  destroyed () {
    if (this.store) {
      window.removeEventListener('beforeunload', this.unsavedChangesPrompt)
    }
  },
  methods: {
    showConfigurationDialog () {
      this.$refs['configure-modal'].show()
    },
    save () {
      this.storage.set('fields', this.fieldsOrder)
      this.storage.set('selection', this.selection)
      if (this.saveStateInternal) {
        this.stateStorage.set('state', this.state)
        this.stateStorage.set('save', true)
      } else {
        this.stateStorage.del('state')
        this.stateStorage.set('save', false)
      }
      this.setInitialData()
    },
    load () {
      this.storage.getKeys().forEach(key => {
        const propName = key[0].toUpperCase() + key.slice(1)
        const data = this.storage.get(key)
        if (data) {
          this['initial' + propName] = data
          this['component' + propName] = data
        }
      })
      const state = this.stateStorage.get('state')
      const save = this.stateStorage.get('save')
      if (save !== undefined) {
        this.saveStateInternal = save
      }
      if (state) {
        this.$emit('update:state', state)
      }
      this.initialSavestate = this.saveStateInternal
      this.dataLoaded = true
    },
    setInitialData () {
      this.initialSelection = this.selection
      this.initialFields = this.fieldsOrder
      this.initialSavestate = this.saveStateInternal
    },
    reset () {
      this.componentSelection = this.initialSelection
      this.componentFields = this.initialFields
      this.saveStateInternal = this.initialSavestate
    },
    resetDefaults () {
      this.componentSelection = this.defaultSelection
      this.componentFields = this.defaultFields
      this.saveStateInternal = this.saveState
    },
    handleCloseOnEscOrBackdrop (event) {
      // https://github.com/bootstrap-vue/bootstrap-vue/issues/3164
      if (event.trigger === 'esc' || event.trigger === 'backdrop') {
        this.reset()
      }
    },
    unsavedChangesPrompt (event) {
      if (this.unsavedChanges) {
        // https://developer.mozilla.org/en-US/docs/Web/API/Window/beforeunload_event
        event.preventDefault()
        return (event.returnValue = '')
      }
    },
  },
}
</script>

<style scoped lang="scss">
  $border: solid 0.6px var(--fs-color-gray-200);;
  @mixin has-border {
    border: $border;
    border-radius: 2px;
  }

  .btn.btn-secondary {
    border-color: var(--theme-dark);
  }

  .modal-body .drag-drop-container {
    margin: -1rem; //counter .modal-body padding
    display: flex;
    align-items: stretch;
    flex-flow: column nowrap;
    gap: 16px;
    padding: 8px 16px;

    ::v-deep >  div {
      display: flex;
      justify-content: flex-start;
      align-items: stretch;
      position: relative;
      @include has-border;

      input, label {
        position: initial;
      }
    }
  }
  .has-border {
    @include has-border
  }
  .checkbox-nest {
    padding: 8px;
    background: var(--fs-color-gray-200);
  }
</style>

<template>
  <b-modal
    ref="input-modal"
    :title="$i18n('chain.inputmodal.title.' + mode)"
    modal-class="bootstrap"
    centered
    size="lg"
    hide-header-close
    no-close-on-backdrop
    no-close-on-esc
    @ok="finishedEditing"
  >
    <form>
      <b-form-group
        :label="$i18n('chain.inputmodal.inputs.name.label')"
        label-for="name-input"
        label-cols-sm="2"
        label-align-sm="right"
        :invalid-feedback="$i18n('chain.inputmodal.inputs.name.invalidfeedback')"
      >
        <b-form-input
          id="name-input"
          v-model="input.name"
          :placeholder="$i18n('chain.inputmodal.inputs.name.placeholder')"
          :formatter="singleSpacing"
          lazy-formatter
          :state="!!input.name"
          maxlength="120"
          trim
        />
      </b-form-group>

      <b-form-group
        id="headquarters-input"
        :label="$i18n('chain.inputmodal.inputs.headquarters.label')"
        label-for="zip-input"
        label-cols-sm="2"
        label-align-sm="right"
      >
        <b-form-input
          id="zip-input"
          v-model="input.headquartersZip"
          :placeholder="$i18n('chain.inputmodal.inputs.headquarters.placeholder.zip')"
          :state="input.headquartersZip ? /^\d{4,5}$/.test(input.headquartersZip) : false"
          maxlength="5"
          trim
        />
        <b-form-input
          id="city-input"
          v-model="input.headquartersCity"
          :placeholder="$i18n('chain.inputmodal.inputs.headquarters.placeholder.city')"
          :formatter="singleSpacing"
          :state="!!input.headquartersCity"
          maxlength="50"
          trim
        />
        <b-form-input
          id="country-input"
          v-model="input.headquartersCountry"
          :placeholder="$i18n('chain.inputmodal.inputs.headquarters.placeholder.country')"
          :formatter="singleSpacing"
          :state="!!input.headquartersCountry"
          maxlength="50"
          trim
        />
      </b-form-group>

      <b-form-group
        :label="$i18n('chain.inputmodal.inputs.estimatedStoreCount.label')"
        label-for="estimatedStoreCount-input"
        label-cols-sm="2"
        label-align-sm="right"
        :invalid-feedback="$i18n('chain.inputmodal.inputs.estimatedStoreCount.invalidfeedback')"
        :description="$i18n('chain.inputmodal.inputs.estimatedStoreCount.description')"
      >
        <b-form-input
          id="estimatedStoreCount-input"
          v-model="input.estimatedStoreCount"
          :state="input.estimatedStoreCount ? /^\d+$/.test(input.estimatedStoreCount) : null"
          trim
        />
      </b-form-group>

      <b-form-group
        :label="$i18n('chain.inputmodal.inputs.status.label')"
        label-for="status-input"
        label-cols-sm="2"
        label-align-sm="right"
      >
        <b-form-select
          id="status-input"
          v-model="input.status"
          :options="statusFilterOptions.slice(1)"
        />
      </b-form-group>

      <b-form-group
        :label="$i18n('chain.inputmodal.inputs.thread.label')"
        label-for="thread-input"
        label-cols-sm="2"
        label-align-sm="right"
        :invalid-feedback="$i18n('chain.inputmodal.inputs.thread.invalidfeedback')"
        :description="$i18n('chain.inputmodal.inputs.thread.description')"
      >
        <forum-search-input
          id="new-foodsaver-search"
          v-model="input.forumThread"
          class="m-1"
          :placeholder="$i18n('chain.inputmodal.inputs.thread.placeholder')"
          :region-id="332"
        />
      </b-form-group>

      <b-form-group
        :label="$i18n('chain.inputmodal.inputs.kam.label')"
        label-for="kams-input"
        label-cols-sm="2"
        label-align-sm="right"
        :invalid-feedback="$i18n('chain.inputmodal.inputs.kam.invalidfeedback')"
        :description="$i18n('chain.inputmodal.inputs.kam.description')"
      >
        <multi-user-search-input
          id="kams-input"
          v-model="input.kamIds"
          class="m-1"
          :placeholder="$i18n('store.sm.searchPlaceholder')"
          button-icon="fa-user-plus"
          :button-tooltip="$i18n('store.sm.makeRegularTeamMember')"
          :region-id="332"
          :disabled="!adminPermissions"
        />
      </b-form-group>

      <b-form-group
        :label="$i18n('chain.inputmodal.inputs.press.label')"
        label-for="press-input"
        label-cols-sm="2"
        label-align-sm="right"
      >
        <b-form-checkbox
          id="press-input"
          v-model="input.allowPress"
          size="lg"
        >
          {{ $i18n('chain.inputmodal.inputs.press.description') }}
        </b-form-checkbox>
      </b-form-group>

      <b-form-group
        :label="$i18n('chain.inputmodal.inputs.notes.label')"
        label-for="notes-input"
        label-cols-sm="2"
        label-align-sm="right"
        :invalid-feedback="$i18n('chain.inputmodal.inputs.notes.invalidfeedback')"
        :description="$i18n('chain.inputmodal.inputs.notes.description')"
      >
        <b-form-textarea
          id="notes-input"
          v-model="input.notes"
          rows="1"
          max-rows="3"
          :state="input.notes ? input.notes.length <= 200 : null"
          :formatter="singleSpacing"
          lazy-formatter
        />
      </b-form-group>

      <b-form-group
        :label="$i18n('chain.inputmodal.inputs.details.label')"
        label-for="details-input"
        label-cols-sm="2"
        label-align-sm="right"
        :description="$i18n('chain.inputmodal.inputs.details.description')"
      >
        <MarkdownInput
          id="details-input"
          :value.sync="input.commonStoreInformation"
          variant="outline-primary"
          :conceal-toolbar="true"
        />
      </b-form-group>
    </form>

    <template #modal-footer="{ ok, cancel }">
      <b-button
        variant="outline-danger"
        @click="cancel()"
      >
        {{ $i18n('chain.inputmodal.cancel') }}
      </b-button>
      <b-button
        variant="primary"
        @click="ok()"
      >
        {{ $i18n('chain.inputmodal.ok') }}
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import { hideLoader, showLoader } from '@/script'
import ForumSearchInput from '@/components/ForumSearchInput.vue'
import MultiUserSearchInput from '@/components/MultiUserSearchInput.vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'

export default {
  components: { ForumSearchInput, MultiUserSearchInput, MarkdownInput },
  props: {
    statusFilterOptions: {
      type: Array,
      required: true,
    },
    adminPermissions: {
      type: Boolean,
      default: false,
    },
  },
  data () {
    return {
      kams: [],
      input: {},
      chainEditing: -1, // The id of the chain to be edited or -1 if a new chain should be created instead
      finishHandle: (chainId, data) => { return true },
    }
  },
  computed: {
    mode () {
      return (this.chainEditing >= 0 ? 'edit' : 'new')
    },
  },
  methods: {
    show (chainEditing, inputData, finishHandle) {
      this.chainEditing = chainEditing
      this.input = inputData
      this.finishHandle = finishHandle
      this.$refs['input-modal'].show()
    },
    singleSpacing (str) {
      return str.trim().replaceAll(/\s+/g, ' ')
    },
    finishedEditing (bvModalEvent) {
      bvModalEvent.preventDefault()
      const data = {
        name: this.input.name,
        headquartersZip: String(this.input.headquartersZip),
        headquartersCity: this.input.headquartersCity,
        headquartersCountry: this.input.headquartersCountry,
        status: Number(this.input.status),
        forumThread: Number(this.input.forumThread) || null,
        estimatedStoreCount: Number(this.input.estimatedStoreCount) || 0,
        allowPress: this.input.allowPress,
        notes: this.input.notes,
        commonStoreInformation: this.input.commonStoreInformation,
        kams: this.input.kamIds ?? [],
      }
      showLoader()
      this.finishHandle(this.chainEditing, data).then((successful) => {
        hideLoader()
        if (successful) {
          this.$refs['input-modal'].hide()
        }
      })
    },
  },
}
</script>

<style lang="scss" scoped>

#headquarters-input::v-deep > div {
  display: flex;

  #zip-input {
    width: 100px;
    margin-right: 1em;
  }
  #city-input {
    width: 100px;
    margin-right: 1em;
  }
}

::v-deep .custom-checkbox.b-custom-control-lg .custom-control-label {
  top: .4rem;

  &::after,
  &::before {
    top: -2px;
  }
}
</style>

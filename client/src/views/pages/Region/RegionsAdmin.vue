<template>
  <div class="row mx-2">
    <Container
      id="region-navigation"
      :title="$i18n('terminology.regions')"
      :collapsible="false"
    >
      <RegionTree
        ref="tree"
        class="list-group-item m-0"
        :include-working-groups="true"
        @update="updateRegionOptions"
        @change="fetchRegionData"
      />
      <button
        class="list-group-item list-group-item-secondary small font-weight-bold"
        @click="prepareNewRegion"
      >
        {{ $i18n('region.new') }}
      </button>
    </Container>
    <Container
      :title="formTitle"
      :collapsible="false"
      class="flex-grow-1"
    >
      <div v-if="region" class="list-group-item">
        <b-form-group :label="$i18n('region.name')">
          <b-form-input
            v-model="region.name"
            :state="region.name ? null : false"
            @change="autofillMailbox"
          />
        </b-form-group>

        <b-form-group :label="$i18n('region.parent')">
          <b-form-select
            v-model="region.parentId"
            :options="regionOptions"
          />
        </b-form-group>
        <b-form-group>
          <slot label>
            {{ $i18n('region.master') }}
            <Info info-key="masterRegion" />
          </slot>
          <b-form-select
            v-model="region.masterId"
            :options="regionOptions"
          />
        </b-form-group>

        <b-form-group :label="$i18n('region.mailbox')">
          <b-form-input
            v-model="region.mailbox"
            :state="mailboxState"
            :formatter="mailboxFormatter"
          />
        </b-form-group>

        <b-form-group :label="$i18n('region.mail.sender')">
          <b-form-input v-model="region.emailName" />
        </b-form-group>

        <b-form-group :label="$i18n('region.type.title')">
          <b-form-select
            v-model="region.type"
            :options="regionTypeOptions"
          />
        </b-form-group>

        <b-form-group
          v-if="isWorkingGroup"
          :label="$i18n('group.function.title')"
        >
          <b-form-select
            v-model="region.workgroupFunction"
            :options="workgroupFunctionOptions"
          />
        </b-form-group>

        <b-form-group :label="$i18n('terminology.' + (isWorkingGroup ? 'admins' : 'ambassadors'))">
          <MultiUserSearchInput
            ref="adminSearch"
            v-model="region.adminIds"
            button-icon="fa-user-plus"
            :region-id="0"
          />
        </b-form-group>

        <div class="float-right">
          <b-button
            v-if="!isNewRegion"
            variant="danger"
            @click="deleteRegion"
          >
            {{ $i18n('button.delete') }}
          </b-button>
          <b-button
            :disabled="!(region.name && mailboxState !== false)"
            variant="success"
            @click="saveRegion"
          >
            {{ $i18n('button.save') }}
          </b-button>
        </div>
      </div>
    </Container>
  </div>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import RegionTree from '@/components/regiontree/RegionTree.vue'
import MultiUserSearchInput from '@/components/MultiUserSearchInput.vue'
import { REGION_UNIT_TYPE, WORKGROUP_FUNCTION } from '@/stores/regions'
import { getRegionData, patchRegion, createRegion } from '@/api/regions'
import { deleteGroup } from '@/api/groups'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'
import { pulseError } from '@/script'
import Info from '@/components/Help/Info.vue'

// TODO preselect no WG function

export default {
  components: { Container, RegionTree, MultiUserSearchInput, Info },
  mixins: [ConfirmationDialogue],
  data () {
    return {
      regionOptions: [],
      region: null,
      workgroupFunctionOptions: [
        { value: 0, text: this.$i18n('group.function.none') },
        ...Object.entries(WORKGROUP_FUNCTION).map(([key, value]) => ({
          value,
          text: this.$i18n(`group.function.${key.toLowerCase()}`),
        })),
      ],
      regionTypeOptions: Object.entries(REGION_UNIT_TYPE).map(([key, value]) => ({
        value,
        text: this.$i18n(`region.type.${key.toLowerCase()}`),
      })),
    }
  },
  computed: {
    isWorkingGroup () {
      return this.region?.type === REGION_UNIT_TYPE.WORKING_GROUP
    },
    formTitle () {
      if (!this.region) return this.$i18n('region.select')
      if (this.region.id) return `${this.$i18n('region.edit')} - ${this.region.name}`
      return this.$i18n('region.createNew')
    },
    isNewRegion () {
      return !this.region.id
    },
    mailboxState () {
      if (!this.region.mailbox) return false
      return /^[\w.\-_]+$/.test(this.region.mailbox) ? null : false
    },
  },
  methods: {
    updateRegionOptions () {
      // TODO disable self and parents
      this.regionOptions = [
        { value: 0, text: this.$i18n('region.root') },
        ...this.$refs.tree.regions.map(region => ({
          value: region.id,
          text: '\u2002'.repeat(region.depth) + region.name,
        })),
      ]
    },
    async fetchRegionData (region) {
      this.region = await getRegionData(region.id)
      await this.$nextTick()
      this.$refs.adminSearch.loadingInitialValues()
    },
    async saveRegion () {
      const dialogueOptions = {
        okVariant: undefined,
        okTitle: this.$i18n('button.save'),
        params: this.region,
      }
      if (!await this.confirmationDialogue(`region.confirm.${this.isNewRegion ? 'create' : 'edit'}`, dialogueOptions)) return
      try {
        this.region = await (this.isNewRegion ? createRegion : patchRegion)(this.region)
        await this.$refs.tree.reset()
      } catch (e) {
        // Simply show the error to the user. Since only orgas use this, this should be user friendly enough.
        pulseError(e?.jsonContent?.message ?? this.$i18n('error_unexpected'))
        this.fetchRegionData(this.region)
      }
    },
    prepareNewRegion () {
      this.region = {
        name: '',
        parentId: this.region?.id ?? 0,
        masterId: this.region?.id ?? 0,
        mailbox: '',
        emailName: '',
        type: 1,
        adminIds: [],
        workgroupFunction: 0,
      }
    },
    async deleteRegion () {
      if (!await this.confirmationDialogue('region.confirm.delete', { params: this.region })) return
      try {
        await deleteGroup(this.region.id)
        await this.$refs.tree.reset()
        this.region = null
      } catch (e) {
        pulseError(e?.jsonContent?.message ?? this.$i18n('error_unexpected'))
      }
    },
    mailboxFormatter (text) {
      const replacements = [
        [' ', '.'],
        ['ä', 'ae'],
        ['ö', 'öe'],
        ['ü', 'ue'],
        ['ß', 'ss'],
      ]
      text = text.toLowerCase()
      for (const replacement of replacements) {
        text = text.replaceAll(...replacement)
      }
      return text
    },
    autofillMailbox (newName) {
      if (!this.region.mailbox) {
        this.region.mailbox = this.mailboxFormatter(newName)
      }
    },
  },
}
</script>
<style lang="scss" scoped>
.row {
  gap: 1em;
}

@media (max-width: 768px) {
  #region-navigation {
    flex-grow: 1;
  }
}

</style>

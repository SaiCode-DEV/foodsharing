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
            :disabled="!region.canEdit"
            @change="autofill"
          />
        </b-form-group>

        <b-form-group :label="$i18n('region.parent')">
          <b-form-select
            v-model="region.parentId"
            :options="regionOptions"
            :disabled="!region.canEdit"
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
            :disabled="!region.canEdit"
          />
        </b-form-group>

        <b-form-group :label="$i18n('region.mailbox')">
          <b-form-input
            v-model="region.mailbox"
            :state="mailboxState"
            :formatter="mailboxFormatter"
            :disabled="!region.canEdit"
          />
        </b-form-group>

        <b-form-group :label="$i18n('region.mail.sender')">
          <b-form-input v-model="region.emailName" :disabled="!region.canEdit" />
        </b-form-group>

        <b-form-group :label="$i18n('region.type.title')">
          <b-form-select
            v-model="region.type"
            :options="regionTypeOptions"
            :disabled="!region.canEdit"
            @change="updateMasterRegion"
          />
        </b-form-group>

        <b-form-group
          v-if="isWorkingGroup"
          :label="$i18n('group.function.title')"
        >
          <b-form-select
            v-model="region.workgroupFunction"
            :options="workgroupFunctionOptions"
            :disabled="!region.canEdit"
            @change="updateModetatorForumDeletion"
          />
        </b-form-group>

        <b-form-group v-if="region.allowHidingInForum !== null">
          <b-form-checkbox
            ref="allowHidingInForum"
            v-model="region.allowHidingInForum"
            :disabled="!region.canEdit"
          >
            {{ $i18n('region.allow_hiding_in_forum') }}
            <Info info-key="moderatorForumHide" />
          </b-form-checkbox>
        </b-form-group>

        <b-form-group :label="$i18n('terminology.' + (isWorkingGroup ? 'admins' : 'ambassadors'))">
          <MultiUserSearchInput
            ref="adminSearch"
            v-model="region.adminIds"
            button-icon="fa-user-plus"
            :region-id="0"
            :disabled="!region.canEdit"
          />
        </b-form-group>

        <div class="float-right">
          <b-button
            v-if="!isNewRegion"
            variant="outline-primary"
            :href="$url('forum', region.id)"
            target="_blank"
            v-text="$i18n('forum.go')"
          />
          <b-button
            v-if="!isNewRegion"
            variant="danger"
            :disabled="!region.canEdit"
            @click="deleteRegion"
            v-text="$i18n('button.delete')"
          />
          <b-button
            :disabled="!(region.name && mailboxState !== false && region.canEdit)"
            variant="success"
            @click="saveRegion"
            v-text="$i18n('button.save')"
          />
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
import { pulseError } from '@/script'
import Info from '@/components/Help/Info.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

// TODO preselect no WG function

export default {
  components: { Container, RegionTree, MultiUserSearchInput, Info },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
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
      if (this.isNewRegion) return this.$i18n('region.createNew')
      return `${this.$i18n('region.edit')} - ${this.region.name}`
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
    async updateRegionOptions () {
      await new Promise(resolve => window.setTimeout(resolve, 100))
      // TODO disable self and parents
      this.regionOptions = [
        { value: 0, text: this.$i18n('region.root') },
        ...this.$refs.tree.getRegions().map(region => ({
          value: region.id,
          text: '\u2002'.repeat(region.depth) + region.name,
        })),
      ]
    },
    async fetchRegionData (region) {
      this.region = await getRegionData(region.states.id)
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
        if (this.region.type !== REGION_UNIT_TYPE.WORKING_GROUP) {
          delete this.region.workgroupFunction
        }
        if (this.isNewRegion) {
          this.region.id = await createRegion(this.region)
          await this.$refs.tree.addRegion(this.region)
        } else {
          await patchRegion(this.region)
          await this.$refs.tree.updateRegion(this.region)
        }
      } catch (e) {
        // Simply show the error to the user. Since only orgas use this, this should be user friendly enough.
        pulseError(e?.jsonContent?.message ?? this.$i18n('error_unexpected'))
        this.fetchRegionData(this.region.id)
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
        id: null,
        allowHidingInForum: false,
        canEdit: true,
      }
      this.$refs.tree.unselect()
    },
    async deleteRegion () {
      if (!await this.confirmationDialogue('region.confirm.delete', { params: this.region })) return
      try {
        await deleteGroup(this.region.id)
        await this.$refs.tree.deleteRegion(this.region)
        this.region = null
      } catch (e) {
        pulseError(e?.jsonContent?.message ?? this.$i18n('error_unexpected'))
      }
    },
    mailboxFormatter (text) {
      const replacements = [
        [' ', '.'],
        ['ä', 'ae'],
        ['ö', 'oe'],
        ['ü', 'ue'],
        ['ß', 'ss'],
      ]
      text = text.toLowerCase()
      for (const replacement of replacements) {
        text = text.replaceAll(...replacement)
      }
      return text
    },
    autofill (newName) {
      if (!this.region.mailbox) {
        this.region.mailbox = this.mailboxFormatter(newName)
      }
      if (!this.region.emailName) {
        this.region.emailName = `foodsharing ${newName}`
      }
    },
    updateMasterRegion () {
      if (this.region.type === REGION_UNIT_TYPE.WORKING_GROUP) {
        this.region.masterId = 0
      } else if (this.region.masterId === 0 && this.region.type !== REGION_UNIT_TYPE.WORKING_GROUP) {
        this.region.masterId = this.region.parentId
      }
    },
    async updateModetatorForumDeletion () {
      this.region.allowHidingInForum = [WORKGROUP_FUNCTION.REPORT, WORKGROUP_FUNCTION.ARBITRATION].includes(this.region.workgroupFunction)
      this.region = Object.assign({}, this.region) // Needed to get a display update
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

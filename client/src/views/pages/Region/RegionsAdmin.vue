<template>
  <div class="row mx-2">
    <Container
      id="region-navigation"
      :title="$t('terminology.regions')"
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
        {{ $t('region.new') }}
      </button>
    </Container>
    <Container
      :title="formTitle"
      :collapsible="false"
      class="flex-grow-1"
    >
      <div v-if="region" class="list-group-item">
        <b-form-group :label="$t('region.name')">
          <b-form-input
            v-model="region.name"
            :state="region.name ? null : false"
            @change="autofill"
          />
        </b-form-group>

        <b-form-group :label="$t('region.parent')">
          <b-form-select
            v-model="region.parentId"
            :options="regionOptions"
          />
        </b-form-group>
        <b-form-group>
          <slot label>
            {{ $t('region.master') }}
            <Info info-key="masterRegion" />
          </slot>
          <b-form-select
            v-model="region.masterId"
            :options="regionOptions"
          />
        </b-form-group>

        <b-form-group :label="$t('region.mailbox')">
          <b-form-input
            v-model="region.mailbox"
            :state="mailboxState"
            :formatter="mailboxFormatter"
          />
        </b-form-group>

        <b-form-group :label="$t('region.mail.sender')">
          <b-form-input v-model="region.emailName" />
        </b-form-group>

        <b-form-group :label="$t('region.type.title')">
          <b-form-select
            v-model="region.type"
            :options="regionTypeOptions"
            @change="updateMasterRegion"
          />
        </b-form-group>

        <b-form-group
          v-if="isWorkingGroup"
          :label="$t('group.function.title')"
        >
          <b-form-select
            v-model="region.workgroupFunction"
            :options="workgroupFunctionOptions"
            @change="updateModetatorForumDeletion"
          />
        </b-form-group>

        <b-form-group v-if="region.allowHidingInForum !== null">
          <b-form-checkbox
            ref="allowHidingInForum"
            v-model="region.allowHidingInForum"
          >
            {{ $t('region.allow_hiding_in_forum') }}
            <Info info-key="moderatorForumHide" />
          </b-form-checkbox>
        </b-form-group>

        <b-form-group :label="$t('terminology.' + (isWorkingGroup ? 'admins' : 'ambassadors'))">
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
            variant="outline-primary"
            :href="$url('forum', region.id)"
            target="_blank"
          >
            {{ $t('forum.go') }}
          </b-button>
          <b-button
            v-if="!isNewRegion"
            variant="danger"
            @click="deleteRegion"
          >
            {{ $t('button.delete') }}
          </b-button>
          <b-button
            :disabled="!(region.name && mailboxState !== false)"
            variant="success"
            @click="saveRegion"
          >
            {{ $t('button.save') }}
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
        { value: 0, text: this.$t('group.function.none') },
        ...Object.entries(WORKGROUP_FUNCTION).map(([key, value]) => ({
          value,
          text: this.$t(`group.function.${key.toLowerCase()}`),
        })),
      ],
      regionTypeOptions: Object.entries(REGION_UNIT_TYPE).map(([key, value]) => ({
        value,
        text: this.$t(`region.type.${key.toLowerCase()}`),
      })),
    }
  },
  computed: {
    isWorkingGroup () {
      return this.region?.type === REGION_UNIT_TYPE.WORKING_GROUP
    },
    formTitle () {
      if (!this.region) return this.$t('region.select')
      if (this.isNewRegion) return this.$t('region.createNew')
      return `${this.$t('region.edit')} - ${this.region.name}`
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
        { value: 0, text: this.$t('region.root') },
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
        okTitle: this.$t('button.save'),
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
        pulseError(e?.jsonContent?.message ?? this.$t('error_unexpected'))
        this.fetchRegionData({ states: { id: this.region.id } })
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
        pulseError(e?.jsonContent?.message ?? this.$t('error_unexpected'))
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

<template>
  <div>
    <div class="field">
      <div class="rounded head ui-widget-header">
        {{ $i18n('group.applications_for', { name: groupName }) }}
      </div>

      <div class="rounded applicants-list ui-widget ui-widget-content">
        <ul class="container linklist">
          <li
            v-for="application in applications"
            :key="application.id"
          >
            <a
              :href="$url('application', groupId, application.id)"
              class="row justify-content-start"
            >
              <Avatar
                class="mr-2"
                :user="application"
                :href="$url('application', groupId, application.id)"
              />
              <span class="d-inline avatar-title">{{ application.name }}</span>
            </a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'

export default {
  components: { Avatar },
  props: {
    applications: { type: Array, required: true },
    groupName: { type: String, required: true },
    groupId: { type: Number, required: true },
  },
}
</script>

<style lang="scss" scoped>
.rounded {
  border-radius: 5px
}

.title {
  font-size: 13px;
  font-weight: normal;
  margin: 0;
  padding: 10px;
}

.applicants-list {
  border: 1px solid var(--fs-border-default);
}

a.row {
  -moz-border-radius: 4px;
  -webkit-border-radius: 4px;
  border-radius: 4px;
}

.avatar-title {
  text-decoration: none;
  color: var(--fs-color-primary-500);
  font-weight: bold;
}
</style>

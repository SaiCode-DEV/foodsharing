<!-- Special case of the ProfileSettingsPage that only shows the component for deleting one's account -->
<template>
  <div>
    <Container :title="$t('foodsaver.delete_account')" wrap-content>
      <DeleteAccount :user-id="userId" />
    </Container>
  </div>
</template>

<script>
import DeleteAccount from './DeleteAccount.vue'
import Container from '@/components/Container/Container.vue'

export default {
  name: 'DeleteAccountPage',
  components: { DeleteAccount, Container },
  props: {
    userId: { type: Number, required: true },
  },
}
</script>

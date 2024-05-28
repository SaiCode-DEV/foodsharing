<template>
  <b-modal
    ref="modal"
    centered
    title="Push-Benachrichtigungen erhalten?"
    ok-title="Push-Benachrichtigungen erhalten"
    cancel-title="nicht aktivieren"
    :cancel-variant="dontAskAgain ? 'outline-danger' : ''"
  >
    Damit du schneller mitbekommst, wenn andere Foodsaver:innen dir Nachrichten schicken, kannst du auf diesem Gerät Push-Benachrichtigungen aktivieren!
    <hr>
    In deinen <a href="#">Benachichtigungseinstellungen</a> kannst du diese Benachrichtigungen jeder Zeit wieder abschalten.
    <hr>
    <b-form-checkbox v-model="dontAskAgain">
      Nicht wieder nachfragen
    </b-form-checkbox>
  </b-modal>
</template>
<script>
export default {
  data: () => ({
    dontAskAgain: false,
  }),
  methods: {
    maybeShow () {
      this.$refs.modal.show()
    },
  },
}
</script>

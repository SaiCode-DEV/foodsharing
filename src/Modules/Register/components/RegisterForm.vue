<template>
  <div class="card rounded">
    <div class="card-header text-white bg-primary">
      {{ $t('register.title') }} ({{ page }} / 6)
    </div>
    <div :class="{disabledLoading: isLoading, 'card-body': true}">
      <RegisterPassword
        v-if="page === 1"
        id="step1"
        :password.sync="password"
        @next="next()"
      />
      <RegisterName
        v-else-if="page === 2"
        id="step2"
        :firstname.sync="firstname"
        :lastname.sync="lastname"
        :gender.sync="gender"
        @prev="prev()"
        @next="next()"
      />
      <RegisterBirthdate
        v-else-if="page === 3"
        id="step3"
        :birthdate.sync="birthdate"
        @prev="prev()"
        @save="birthdateSave"
        @next="next()"
      />
      <RegisterMobilephone
        v-else-if="page === 4"
        id="step4"
        @update-mobile-number="updateMobileNumber"
        @prev="prev()"
        @next="next()"
      />
      <RegisterLegalAgreement
        v-else-if="page === 5"
        id="step5"
        :subscribe-newsletter.sync="subscribeNewsletter"
        :accept-gdpr.sync="acceptGdpr"
        :accept-legal.sync="acceptedLegal"
        @prev="prev()"
        @submit="submit()"
      />
      <RegisterSuccess
        v-if="page === 6"
        id="step6"
        :newsletter-subscription-failed="newsletterSubscriptionFailed"
        @load-login="loadLogin()"
      />
    </div>
  </div>
</template>

<script>
import { registerUser } from '@/api/user'
import { pulseSuccess, pulseError } from '@/script'
import { navigate } from '@/helper/router'
import i18n from '@/helper/i18n'
import RegisterPassword from './RegisterPassword.vue'
import RegisterName from './RegisterName'
import RegisterBirthdate from './RegisterBirthdate'
import RegisterMobilephone from './RegisterMobilephone'
import RegisterLegalAgreement from './RegisterLegalAgreement'
import RegisterSuccess from './RegisterSuccess'

export default {
  components: {
    RegisterPassword,
    RegisterName,
    RegisterBirthdate,
    RegisterMobilephone,
    RegisterLegalAgreement,
    RegisterSuccess,
  },
  props: {
    token: { type: String, required: true },
  },
  data () {
    return {
      page: 1,
      isLoading: false,
      password: '',
      firstname: '',
      lastname: '',
      gender: null,
      birthdate: null,
      mobile: null,
      subscribeNewsletter: false,
      acceptGdpr: false,
      acceptedLegal: false,
      newsletterSubscriptionFailed: true,
    }
  },
  methods: {
    updateMobileNumber (data) {
      this.mobile = data.value
    },
    prev () {
      this.page--
    },
    next () {
      this.page++
    },
    birthdateSave (v) {
      this.birthdate = v
    },
    loadLogin () {
      navigate(this.$url('login'))
    },
    async submit () {
      this.isLoading = true

      try {
        const registerResult = await registerUser(this.firstname, this.lastname, this.token, this.password, this.gender, this.birthdate,
          this.mobile, this.subscribeNewsletter ? 1 : 0)
        this.newsletterSubscriptionFailed = registerResult.hasNewsletterSubscriptionFailed
        this.page = 6
        pulseSuccess(i18n('register.join_success'))
      } catch (err) {
        pulseError(`${i18n('register.join_error')}<br><br> ${err.message}`)
      }
      this.isLoading = false
    },
  },
}
</script>

<style>
.bootstrap .form-inline {
  display: none;
}

</style>

<style lang="scss" scoped>
.bootstrap {
  ::v-deep .form-control {
      background-color: var(--fs-color-light);
  }

  .invalid-feedback {
    font-size: 100%;
    display: unset;
  }

  .input-group {
    input.form-control {
      padding-left: 12px;
    }

    .input-group-text {
      background-color: var(--fs-color-primary-300);
      padding-left: 12px;
    }
  }

  ::v-deep .custom-control-label {
    font-size: 1rem;
    &::before {
      top: .1rem;
    }
  }
}
</style>

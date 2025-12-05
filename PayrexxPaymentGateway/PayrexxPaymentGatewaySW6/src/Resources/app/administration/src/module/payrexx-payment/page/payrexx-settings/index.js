const {Component, Mixin} = Shopware;

import template from './payrexx-settings.html.twig';
import './style.scss';

Component.register('payrexx-settings', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    inject: [ 'PayrexxPaymentSettingsService' ],

    data() {
        return {
            config: {},
            isLoading: false,
            isTesting: false,
            isSaveSuccessful: false,
            isTestSuccessful: false,
            instanceNameFilled: false,
            apiKeyFilled: false,
            showValidationErrors: false,
            isSupportModalOpen: false,
        }
    },
    computed: {
        credentialsMissing: function () {
            return !this.instanceNameFilled || !this.apiKeyFilled;
        }
    },
    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isLoading = true;
            if (this.credentialsMissing) {
                this.showValidationErrors = true;
                this.isLoading = false;
                return;
            }

            this.isSaveSuccessful = false;
            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onTest() {
            this.isTesting = true;
            this.isTestSuccessful = false;

            let credentials = {
                instanceName: this.getConfigValue('instanceName'),
                apiKey: this.getConfigValue('apiKey'),
                platform: this.getConfigValue('platform'),
            };

            this.PayrexxPaymentSettingsService.validateApiCredentials(credentials).then((response) => {
                const credentialsValid = response.credentialsValid;
                const error = response.error;

                if (credentialsValid) {
                    this.createNotificationSuccess({
                        title: this.$tc('payrexx-payment.settingsForm.messages.titleSuccess'),
                        message: this.$tc('payrexx-payment.settingsForm.messages.messageTestSuccess')
                    });
                    this.isTestSuccessful = true;
                } else {
                    this.createNotificationError({
                        title: this.$tc('payrexx-payment.settingsForm.messages.titleError'),
                        message: this.$tc('payrexx-payment.settingsForm.messages.messageTestError')
                    });
                }
                this.isTesting = false;
            }).catch((errorResponse) => {
                this.createNotificationError({
                    title: this.$tc('payrexx-payment.settingsForm.messages.titleError'),
                    message: this.$tc('payrexx-payment.settingsForm.messages.messageTestErrorGeneral')
                });
                this.isTesting = false;
            });
        },

        onConfigChange(config) {
            this.config = config;

            this.checkCredentialsFilled();

            this.showValidationErrors = false;

            dispatchEvent(new CustomEvent('payrexx-config-change', { detail: config }));
        },

        getBind(element, config) {
            if (config !== this.config) {
                this.onConfigChange(config);
            }

            if (this.showValidationErrors) {
                if (element.name === 'PayrexxPaymentGatewaySW6.settings.instanceName' && !this.instanceNameFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('payrexx-payment.settingsForm.messages.messageNotBlank')
                    };
                }
                if (element.name === 'PayrexxPaymentGatewaySW6.settings.apiKey' && !this.apiKeyFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('payrexx-payment.settingsForm.messages.messageNotBlank')
                    };
                }
            }

            return element;
        },


        checkCredentialsFilled() {
            this.instanceNameFilled = !!this.getConfigValue('instanceName');
            this.apiKeyFilled = !!this.getConfigValue('apiKey');
        },
        getConfigValue(field) {
            const defaultConfig = this.$refs.systemConfig.actualConfigData.null;
            const salesChannelId = this.$refs.systemConfig.currentSalesChannelId;

            if (salesChannelId === null) {
                return this.config[`PayrexxPaymentGatewaySW6.settings.${field}`];
            }
            return this.config[`PayrexxPaymentGatewaySW6.settings.${field}`]
                || defaultConfig[`PayrexxPaymentGatewaySW6.settings.${field}`];
        },
    }
});

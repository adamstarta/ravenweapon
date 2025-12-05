const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class PayrexxPaymentSettingsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'payrexx_payment') {
        super(httpClient, loginService, apiEndpoint);
    }

    validateApiCredentials(credentials) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/validate-api-credentials`,
                {
                    credentials: credentials,
                },
                {
                    headers: headers
                }
            )
            .then((response) => {
            return ApiService.handleResponse(response);
    });
    }

}

Application.addServiceProvider('PayrexxPaymentSettingsService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PayrexxPaymentSettingsService(initContainer.httpClient, container.loginService);
});


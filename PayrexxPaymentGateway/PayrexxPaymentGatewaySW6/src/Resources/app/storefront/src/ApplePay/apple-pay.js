import Plugin from 'src/plugin-system/plugin.class';

export default class ApplePay extends Plugin {
    init() {
        if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
            jQuery("#payrexx-applepay").parent('.payment-method').hide();
            console.warn("Payrexx Apple Pay is not supported on this device/browser");
        }
    }
}

import './components/payrexx-payment-settings-icon';
import './extension/sw-settings-index';
import './page/payrexx-settings';

import deDE from './snippet/de_DE.json';
import deCH from './snippet/de_CH.json';
import enGB from './snippet/en_GB.json';


const { Module } = Shopware;

Module.register('payrexx-payment', {
    type: 'plugin',
    name: 'PayrexxPayment',
    title: 'payrexx-payment.module.title',
    description: 'payrexx-payment.module.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    icon: 'regular-cog',

    snippets: {
        'de-DE': deDE,
        'de-CH': deCH,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'payrexx-settings',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },
    settingsItem: {
        name: 'payrexx-payment-settings',
        group: 'plugins',
        to: 'payrexx.payment.index',
        // iconComponent: 'payrexx-payment-settings-icon',
        icon: 'regular-credit-card',
        backgroundEnabled: true,
    }
});

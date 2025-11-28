import RavenOffcanvasCartPlugin from './plugin/raven-offcanvas-cart.plugin';

const PluginManager = window.PluginManager;

PluginManager.register('RavenOffcanvasCart', RavenOffcanvasCartPlugin, 'body');

import RavenOffcanvasCartPlugin from './plugin/raven-offcanvas-cart.plugin';
import RavenToastPlugin from './plugin/raven-toast.plugin';

const PluginManager = window.PluginManager;

PluginManager.register('RavenOffcanvasCart', RavenOffcanvasCartPlugin, 'body');
PluginManager.register('RavenToast', RavenToastPlugin, 'body');

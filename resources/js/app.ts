/**
 * e-Hajiri SPA entry point.
 *
 * Wires up Vue, Pinia, Vue Router, vue-i18n, and the v-can directive.
 * Module routes and stores are registered from resources/js/modules/.
 */
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';

import App from '@/App.vue';
import { router } from '@/router';
import { canDirective } from '@/directives/can';
import en from '@/locales/en.json';

const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: { en },
});

createApp(App)
    .use(createPinia())
    .use(router)
    .use(i18n)
    .directive('can', canDirective)
    .mount('#app');

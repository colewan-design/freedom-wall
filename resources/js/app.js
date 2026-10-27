import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import SiteNotice from './Components/SiteNotice.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Set the palette before first paint. Layouts keep this in sync when the theme
// is toggled, but the auth and admin-login screens render without a layout, so
// without this they would resolve the light tokens on their dark backgrounds.
try {
    const saved = localStorage.getItem('wall-theme');
    document.documentElement.setAttribute('data-theme', saved === 'light' ? 'light' : 'dark');
} catch {
    document.documentElement.setAttribute('data-theme', 'dark');
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // SiteNotice sits beside the page rather than inside a layout: the auth
        // and admin-login screens render without one, and mounting it here also
        // keeps a dismissal from resetting on every Inertia navigation.
        return createApp({ render: () => [h(App, props), h(SiteNotice)] })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

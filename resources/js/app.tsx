import '../css/app.css';
import './bootstrap';
import '@/assets/images/favicon.ico';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { AppProviders } from '@/app/providers/app-providers';

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) || 'COP';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <AppProviders>
                <App {...props} />
            </AppProviders>
        );
    },
    progress: {
        color: '#ff0000',
        showSpinner: false,
        includeCSS: true,
        delay: 80,
    },
});

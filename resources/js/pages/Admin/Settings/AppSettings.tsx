import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types/inertia';
import { Head, router, usePage } from '@inertiajs/react';
import { HardDrive, Mail, Users } from 'lucide-react';
import { useMemo } from 'react';
import type { CustomerHierarchyPayload } from './components/customer-hierarchy-types';
import { CustomerEngineerSettings } from './components/CustomerEngineerSettings';
import { MailSettingsCard, MailSettingsProps } from './components/MailSettingsCard';
import { MediaQuotaSettingsCard } from './components/MediaQuotaSettingsCard';

type AdminAppSettingsPageProps = PageProps<{
    media_quotas?: Record<string, number | null>;
    media_config?: {
        max_file_mb?: number;
        chunk_size_mb?: number;
        allowed_mimes?: string[];
    };
    mail_settings?: MailSettingsProps;
    customer_hierarchy?: CustomerHierarchyPayload;
}>;

type SettingsTab = 'customers' | 'storage' | 'mail';

const TAB_BUTTON_CLASS =
    'inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-all cursor-pointer whitespace-nowrap';

function tabButtonClass(active: boolean): string {
    return cn(
        TAB_BUTTON_CLASS,
        active
            ? 'border-primary text-primary bg-primary/5 rounded-t-lg'
            : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
    );
}

export function AdminAppSettings() {
    useFlashToasts();

    const { media_quotas, media_config, mail_settings, customer_hierarchy } =
        usePage<AdminAppSettingsPageProps>().props;

    // The controller withholds admin-only props from managers, so prop presence
    // is the single source of truth for which tabs exist.
    const availableTabs = useMemo<SettingsTab[]>(() => {
        const tabs: SettingsTab[] = [];

        if (customer_hierarchy) tabs.push('customers');
        if (media_quotas) tabs.push('storage');
        if (mail_settings) tabs.push('mail');

        return tabs;
    }, [customer_hierarchy, media_quotas, mail_settings]);

    const activeTab = useMemo(() => {
        const param = new URLSearchParams(window.location.search).get('tab') as SettingsTab | null;

        return param && availableTabs.includes(param) ? param : (availableTabs[0] ?? 'customers');
    }, [availableTabs]);

    const setActiveTab = (tab: SettingsTab) => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        router.visit(url.toString(), { preserveScroll: true, replace: true });
    };

    return (
        <AppLayout
            title="App Settings"
            breadcrumb={[{ label: 'App Settings' }]}
        >
            <Head title="App Settings" />
            <div className="space-y-6 max-w-5xl">
                {/* Enterprise Tab Navigation */}
                <div className="flex items-center gap-2 border-b border-border/70 pb-px overflow-x-auto">
                    {customer_hierarchy && (
                        <button
                            type="button"
                            onClick={() => setActiveTab('customers')}
                            className={tabButtonClass(activeTab === 'customers')}
                        >
                            <Users className="size-4" />
                            <span>Customer Account Settings</span>
                        </button>
                    )}

                    {media_quotas && (
                        <button
                            type="button"
                            onClick={() => setActiveTab('storage')}
                            className={tabButtonClass(activeTab === 'storage')}
                        >
                            <HardDrive className="size-4" />
                            <span>Media &amp; Storage Quotas</span>
                        </button>
                    )}

                    {mail_settings && (
                        <button
                            type="button"
                            onClick={() => setActiveTab('mail')}
                            className={tabButtonClass(activeTab === 'mail')}
                        >
                            <Mail className="size-4" />
                            <span>Email Sending (SMTP)</span>
                        </button>
                    )}
                </div>

                {/* Tab Content Panels */}
                <div className="pt-2">
                    {activeTab === 'customers' && customer_hierarchy && (
                        <div className="animate-in fade-in-50 duration-200">
                            <CustomerEngineerSettings hierarchy={customer_hierarchy} />
                        </div>
                    )}

                    {activeTab === 'storage' && media_quotas && (
                        <div className="animate-in fade-in-50 duration-200">
                            <MediaQuotaSettingsCard
                                mediaQuotas={media_quotas}
                                mediaConfig={media_config}
                                activeTab={activeTab}
                            />
                        </div>
                    )}

                    {activeTab === 'mail' && mail_settings && (
                        <div className="animate-in fade-in-50 duration-200">
                            <MailSettingsCard settings={mail_settings} activeTab={activeTab} />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

export default AdminAppSettings;

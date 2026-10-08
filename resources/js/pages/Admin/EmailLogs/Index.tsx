import { EmailLogsPage } from '@/components/email-logs/email-logs-page';

export function AdminEmailLogsIndex() {
    return (
        <EmailLogsPage
            routeName="admin.email-logs.index"
            destroyRouteName="admin.email-logs.destroy"
            title="Email Audit Logs"
            breadcrumbLabel="Admin"
        />
    );
}

export default AdminEmailLogsIndex;

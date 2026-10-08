import { EmailLogsPage } from '@/components/email-logs/email-logs-page';

export function ManagerEmailLogsIndex() {
    return (
        <EmailLogsPage
            routeName="manager.email-logs.manager"
            title="Email Audit Log"
            breadcrumbLabel="Manager"
        />
    );
}

export default ManagerEmailLogsIndex;

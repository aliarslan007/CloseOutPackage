import { ProjectFormFields } from '@/components/projects/project-form';
import {
    CustomerOptionItem,
    EngineersByCustomerAndMarket,
    ProjectFormValues,
} from '@/components/projects/project-form-types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import * as React from 'react';

export type ProjectCreatePageProps = PageProps<{
    markets: Array<{ id: number; name: string }>;
    customers?: CustomerOptionItem[];
    engineers?: EngineersByCustomerAndMarket;
    managers: Array<{ id: number; name: string; email: string }>;
    supervisors: Array<{ id: number; name: string; email: string }>;
}>;

export function ProjectCreate() {
    useFlashToasts();

    const { markets, customers, engineers, managers, supervisors } =
        usePage<ProjectCreatePageProps>().props;

    const form = useForm<ProjectFormValues>({
        market_id: '',
        customer_id: '',
        customer_engineer_id: '',
        name: '',
        ps_number: '',
        project_number: '',
        address: '',
        city: '',
        state: '',
        zip: '',
        completed_date: '',
        awarded_date: '',
        rec_issued_date: '',
        construction_started_date: '',
        site_completed_date: '',
        notes: '',
        status: 'pending',
        manager_id: '',
        supervisor_ids: [],
        site_photo: null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('projects.store'));
    };

    return (
        <AppLayout title="Create Project" breadcrumb={[{ label: 'Projects', href: route('projects.index') }, { label: 'Create' }]}>
            <Head title="Create Project" />
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-bold">Create Project</CardTitle>
                        <CardDescription>
                            Capture the site details, customer engineer sign-off, and market scope.
                            The region default engineer is pre-selected automatically.
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit} noValidate>
                        <CardContent>
                            <ProjectFormFields
                                form={form}
                                markets={markets}
                                customers={customers}
                                engineers={engineers}
                                managers={managers}
                                supervisors={supervisors}
                                sitePhotoUrl={null}
                            />
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('projects.index')}>Cancel</Link>
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                Create Project
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}

export default ProjectCreate;

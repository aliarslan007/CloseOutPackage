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

export interface EditProjectProp {
    id: number;
    hash_id?: string;
    name: string;
    ps_number?: string | null;
    project_number?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    zip?: string | null;
    completed_date?: string | null;
    awarded_date?: string | null;
    rec_issued_date?: string | null;
    construction_started_date?: string | null;
    site_completed_date?: string | null;
    notes?: string | null;
    status: string;
    market_id: number;
    customer_id?: number | null;
    customer_engineer_id?: number | null;
    manager_id?: number | null;
    field_supervisors?: Array<{ id: number }>;
}

export type ProjectEditPageProps = PageProps<{
    project: EditProjectProp;
    sitePhotoUrl?: string | null;
    markets: Array<{ id: number; name: string }>;
    customers?: CustomerOptionItem[];
    engineers?: EngineersByCustomerAndMarket;
    managers: Array<{ id: number; name: string; email: string }>;
    supervisors: Array<{ id: number; name: string; email: string }>;
}>;

export function ProjectEdit() {
    useFlashToasts();

    const { project, sitePhotoUrl, markets, customers, engineers, managers, supervisors } =
        usePage<ProjectEditPageProps>().props;

    const form = useForm<ProjectFormValues>({
        market_id: project.market_id,
        customer_id: project.customer_id ?? '',
        customer_engineer_id: project.customer_engineer_id ?? '',
        name: project.name,
        ps_number: project.ps_number ?? '',
        project_number: project.project_number ?? '',
        address: project.address ?? '',
        city: project.city ?? '',
        state: project.state ?? '',
        zip: project.zip ?? '',
        completed_date: project.completed_date?.slice(0, 10) ?? '',
        awarded_date: project.awarded_date?.slice(0, 10) ?? '',
        rec_issued_date: project.rec_issued_date?.slice(0, 10) ?? '',
        construction_started_date: project.construction_started_date?.slice(0, 10) ?? '',
        site_completed_date: project.site_completed_date?.slice(0, 10) ?? '',
        notes: project.notes ?? '',
        status: project.status as ProjectFormValues['status'],
        manager_id: project.manager_id ?? '',
        supervisor_ids: project.field_supervisors?.map((s) => s.id) ?? [],
        site_photo: null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            // Method spoofing: PHP only populates $_POST/$_FILES for POST requests,
            // so a PUT with multipart/form-data (site photo) would lose every
            // field. Sending _method=put keeps update semantics while letting PHP
            // read the payload.
            _method: 'put',
            market_id: data.market_id,
            name: data.name,
            ps_number: data.ps_number,
            project_number: data.project_number,
            address: data.address,
            city: data.city,
            state: data.state,
            zip: data.zip,
            customer_id: data.customer_id,
            customer_engineer_id: data.customer_engineer_id,
            completed_date: data.completed_date,
            awarded_date: data.awarded_date,
            rec_issued_date: data.rec_issued_date,
            construction_started_date: data.construction_started_date,
            site_completed_date: data.site_completed_date,
            notes: data.notes,
            manager_id: data.manager_id,
            supervisor_ids: data.supervisor_ids,
            site_photo: data.site_photo,
        }));
        form.post(route('projects.update', project.hash_id || project.id));
    };

    return (
        <AppLayout title="Edit Project" breadcrumb={[{ label: 'Projects', href: route('projects.index') }, { label: 'Edit' }]}>
            <Head title={`Edit ${project.name}`} />
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-bold">Edit Project</CardTitle>
                        <CardDescription>
                            Update site details and the customer engineer of record. Project status is managed from the
                            project overview, not here.
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
                                sitePhotoUrl={sitePhotoUrl}
                            />
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('projects.show', project.hash_id || project.id)}>Cancel</Link>
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                Save Changes
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}

export default ProjectEdit;

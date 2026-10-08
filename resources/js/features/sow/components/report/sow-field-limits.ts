/**
 * Client-side mirror of the backend `max:*` rules in StoreSowReportRequest
 * and UpdateSowReportRequest. Keep in sync when validation changes.
 */
export const SOW_FIELD_LIMITS = {
    title: 120,
    site_name: 120,
    fiber_provider: 80,
    site_address: 250,
    site_city: 120,
    site_county: 120,
    project_description: 200,
    provider_contact_name: 255,
    provider_contact_phone: 50,
    provider_contact_email: 255,
    demarcation: 100,
    ps_number: 20,
    survey_contractor_name: 255,
    survey_contact_name: 255,
    survey_contact_phone: 50,
    survey_contact_email: 255,
    scope_of_work_description: 350,
    site_access_notes: 2500,
} as const;

export type SowLimitedField = keyof typeof SOW_FIELD_LIMITS;

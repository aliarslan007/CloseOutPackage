import type { DocumentCategory } from './types';

/** The REC collection keeps its dedicated routes/controller. */
export const REC_COLLECTION = 'rec_documents';

/**
 * The six Project Documents Hub categories. `collection` is the media
 * collection name and the key into the `projectDocuments` prop; `slug` is the
 * route segment for the generic document routes.
 */
export const DOCUMENT_CATEGORIES: DocumentCategory[] = [
    {
        collection: 'rec_documents',
        slug: 'rec',
        label: 'REC',
        description: 'Customer-issued REC documents.',
    },
    {
        collection: 'ffr_sow_documents',
        slug: 'ffr-sow',
        label: 'FFR / SOW',
        description: 'Field survey and scope-of-work files.',
    },
    {
        collection: 'third_party_compliance_documents',
        slug: 'third-party-compliance',
        label: 'Third-Party Compliance Docs',
        description: 'Compliance documents supplied by third parties.',
    },
    {
        collection: 'landlord_compliance_documents',
        slug: 'landlord-compliance',
        label: 'Landlord Compliance Doc',
        description: 'Landlord-issued compliance documents.',
    },
    {
        collection: 'cd_documents',
        slug: 'cd',
        label: 'CD',
        description: 'Change-order documents.',
    },
    {
        collection: 'project_email_documents',
        slug: 'project-emails',
        label: 'Project Related Emails',
        description: 'Project email threads exported to file.',
    },
];

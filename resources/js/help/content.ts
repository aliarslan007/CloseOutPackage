import type { HelpArticle, HelpCategory, HelpCategoryId, HelpLink } from './types';

/* eslint-disable sonarjs/no-duplicate-string -- this is a content data file; repetitive plain-language phrases (e.g. "Go to my projects") are intentional and would be unreadable as constants. */

/**
 * Help Center content registry — the single source of truth for every
 * article shown in the help center.
 *
 * Written deliberately for people with no technical background:
 *  - organised around questions ("How do I …?"), not feature names
 *  - short sentences, everyday words, no jargon
 *  - every article carries "Take me there" buttons that open the real
 *    screen in the application
 *  - roles control which articles each type of user sees
 */

export const HELP_CATEGORIES: HelpCategory[] = [
    {
        id: 'getting-started',
        label: 'Getting started',
        description: 'New here? A friendly tour of the basics.',
        icon: 'rocket',
        roles: ['admin', 'manager', 'field_supervisor', 'customer'],
    },
    {
        id: 'projects',
        label: 'Projects & visibility',
        description: 'Your projects, supervisors, and live progress.',
        icon: 'building',
        roles: ['admin', 'manager', 'field_supervisor', 'customer'],
    },
    {
        id: 'packages',
        label: 'Closeout packages',
        description: 'Create packages, upload photos, answer checklists.',
        icon: 'folder',
        roles: ['admin', 'manager', 'field_supervisor', 'customer'],
    },
    {
        id: 'photos',
        label: 'Photos',
        description: 'Upload, download, and fix photos.',
        icon: 'camera',
        roles: ['admin', 'manager', 'field_supervisor', 'customer'],
    },
    {
        id: 'review',
        label: 'Review & approval',
        description: 'Approve photos and packages step by step.',
        icon: 'clipboard',
        roles: ['admin', 'manager'],
    },
    {
        id: 'pdfs',
        label: 'PDFs & emails',
        description: 'Create, send, and download PDF reports.',
        icon: 'file',
        roles: ['admin', 'manager', 'field_supervisor', 'customer'],
    },
    {
        id: 'admin',
        label: 'Admin & management',
        description: 'Markets, users, templates, and audit logs.',
        icon: 'settings',
        roles: ['admin', 'manager'],
    },
    {
        id: 'communication',
        label: 'Chat',
        description: 'Talk with your team, right inside a project.',
        icon: 'chat',
        roles: ['manager', 'field_supervisor'],
    },
    {
        id: 'customer',
        label: 'Customer portal',
        description: 'Everything customers can see and download.',
        icon: 'home',
        roles: ['customer'],
    },
];

export const HELP_ARTICLES: HelpArticle[] = [
    /* ------------------------------------------------------------------ */
    /* Getting started                                                    */
    /* ------------------------------------------------------------------ */
    {
        slug: 'welcome',
        title: 'Getting around COP',
        summary: 'A friendly first tour — where the menus are and how to move around.',
        roles: ['admin', 'manager', 'field_supervisor', 'customer'],
        category: 'getting-started',
        icon: 'sparkles',
        minutes: 2,
        keywords: ['tour', 'menu', 'navigation', 'first time', 'where do i click', 'buttons'],
        intro:
            'Welcome! This short tour shows you where things live so you never feel lost. You do not need any technical knowledge — if you can use a phone, you can use COP.',
        steps: [
            {
                title: 'Sign in',
                body: 'Open the link you received by email. Sign in with your work account the same way you sign in to your email. If you get stuck, your manager or the person who invited you can help.',
            },
            {
                title: 'Look at the menu on the left',
                body: 'The left hand menu is your map. Each person sees the items that belong to their job — you will only ever see things you are allowed to use. For example, only managers see the review tools, and only customers see the customer portal.',
            },
            {
                title: 'Use the search button',
                body: 'In the top bar there is a magnifying glass button. Click it to jump straight to a screen by name, such as "Projects" or "Templates".',
            },
            {
                title: 'Come back to the Help Center any time',
                body: 'The round "?" button in the top bar (and "Help Center" in the left menu) always brings you back here. If you are ever confused, that is the first place to look.',
                tip: 'You cannot break anything by clicking around. Everything you change has a confirmation step, and nothing is deleted without you saying yes.',
            },
        ],
        faqs: [
            {
                question: 'I signed in but the page looks different from my colleague\'s. Is something wrong?',
                answer: 'No — everything is fine. Different jobs see different screens. That is on purpose, so nobody is distracted by tools they do not need.',
            },
        ],
        featured: true,
    },
    {
        slug: 'glossary',
        title: 'Words we use, explained simply',
        summary: 'A plain language dictionary of the terms you will see in COP.',
        roles: ['admin', 'manager', 'field_supervisor', 'customer'],
        category: 'getting-started',
        icon: 'book',
        minutes: 2,
        keywords: ['terms', 'dictionary', 'what does', 'meaning', 'jargon', 'definitions'],
        intro:
            'Some words in COP come from the construction world. Here is what they mean, in plain English.',
        steps: [
            {
                title: 'Project',
                body: 'One construction job — for example, one site. Everything else (supervisors, packages, photos) belongs to a project.',
            },
            {
                title: 'Closeout package (or just "package")',
                body: 'The photo and checklist record for a project. It shows, step by step, that the work on the site is complete and looks right.',
            },
            {
                title: 'Step (or stage)',
                body: 'One part of the package, such as Pre Construction or Construction. You finish one step, then move to the next.',
            },
            {
                title: 'Template',
                body: 'A ready made checklist that tells supervisors which photos and answers are needed for each step. Templates keep every project looking the same.',
            },
            {
                title: 'Checklist',
                body: 'The list of yes/no questions inside a step. It proves that each item was checked on site.',
            },
            {
                title: 'Flag',
                body: 'A photo marked by the manager as blurry or not good enough. The supervisor replaces it with a better one.',
            },
            {
                title: 'Replacement requested',
                body: 'The manager asked for a new photo instead of the current one. Same idea as a flag — take a fresh photo and replace it.',
            },
            {
                title: 'Draft',
                body: 'Work in progress. A draft is saved but not sent for review yet, and can still be changed or deleted.',
            },
            {
                title: 'Submit',
                body: 'Send the package to the manager for review. After submitting, you cannot change it until the manager sends it back.',
            },
            {
                title: 'Approved',
                body: 'The manager checked everything and accepted it. Only approved packages can produce the final PDF report.',
            },
            {
                title: 'PDF report',
                body: 'A clean, printable document with all the photos and answers, ready to send to the client. You make a progress PDF during the work and a final PDF at the end.',
            },
            {
                title: 'Market',
                body: 'A region the business works in, such as Ohio or Texas. Projects and users are organised by market.',
            },
            {
                title: 'Live visibility',
                body: 'A read only screen where managers (and admins) can see, at a glance, how every project is going — what is not started, what is in progress, and what is done.',
            },
        ],
    },

    /* ------------------------------------------------------------------ */
    /* Projects & visibility                                              */
    /* ------------------------------------------------------------------ */
    {
        slug: 'my-projects',
        title: 'See the projects assigned to me',
        summary: 'Find your projects, search by name, and filter by market.',
        roles: ['manager', 'field_supervisor'],
        category: 'projects',
        icon: 'building',
        minutes: 2,
        keywords: ['assigned', 'my projects', 'filter', 'sort', 'search', 'find'],
        intro:
            'Your projects are the ones you are working on. Here is how to find them quickly, even when there are many.',
        steps: [
            {
                title: 'Open Projects',
                body: 'Click "Projects" in the left hand menu. You will see a list of the projects you can work on.',
                links: [{ label: 'Go to my projects', route: 'projects.index' }],
            },
            {
                title: 'Search by name',
                body: 'Type part of the project name into the search box — for example "Ohio" or "Main St" — and the list narrows down as you type.',
            },
            {
                title: 'Filter by market',
                body: 'If your company works in more than one market (like Ohio and Texas), use the market filter to see only the projects from one market.',
            },
            {
                title: 'Sort the list',
                body: 'Use the sort menu to order projects the way you like — newest first, or by name from A to Z. The list keeps your choice next time you visit.',
            },
            {
                title: 'Open a project',
                body: 'Click the project name to open its page, where you can see the details, photos, and closeout packages.',
            },
        ],
        featured: true,
    },
    {
        slug: 'manager-project-details',
        title: 'View or edit a project',
        summary: 'Open a project page, see its details, and make changes when needed.',
        roles: ['manager'],
        category: 'projects',
        icon: 'building',
        minutes: 2,
        keywords: ['edit project', 'details', 'address', 'market', 'change', 'update'],
        intro:
            'Every project has its own page with all the important information in one place.',
        steps: [
            {
                title: 'Open the project',
                body: 'Go to Projects and click the project you want. If there are many, use the search box to find it fast.',
                links: [{ label: 'Go to my projects', route: 'projects.index' }],
            },
            {
                title: 'See the project details',
                body: 'The top of the page shows the project name, address, market, and completion progress.',
            },
            {
                title: 'See who is working on it',
                body: 'You will find the manager, the field supervisors assigned to the project, and the crew. Issues on the site and the site photo are on the same page too.',
            },
            {
                title: 'See the closeout packages',
                body: 'The package card lists every closeout package for this project and its status, so you always know what is happening.',
            },
            {
                title: 'Edit the project',
                body: 'If something needs to change (for example the address or the market), click the Edit button, make your changes, and click Save.',
            },
            {
                title: 'Where is the site?',
                body: 'The project page shows the site location, so you can see exactly where the job is.',
            },
        ],
    },
    {
        slug: 'manager-assign-supervisor',
        title: 'Assign a supervisor to a project',
        summary: 'Add one or several supervisors to a project so they can start working.',
        roles: ['manager'],
        category: 'projects',
        icon: 'users',
        minutes: 3,
        keywords: ['assign', 'supervisor', 'add', 'crew', 'team', 'assign people'],
        intro:
            'Before a supervisor can upload photos for a project, they need to be assigned to it. You can assign one supervisor or several at the same time.',
        steps: [
            {
                title: 'Open the project',
                body: 'Go to Projects and click the project you want to add people to.',
                links: [{ label: 'Go to my projects', route: 'projects.index' }],
            },
            {
                title: 'Find the Supervisors section',
                body: 'On the project page, look for the section called Supervisors (or Field Supervisors).',
            },
            {
                title: 'Click "Add supervisor"',
                body: 'A list of available users opens. You can pick one name or several names at once.',
            },
            {
                title: 'Save',
                body: 'Click Save or Add. The supervisors now appear on the project, and the project appears in their list of assigned projects.',
                tip: 'Only people with a supervisor account appear in the list. If someone is missing, an admin needs to create their account first.',
            },
        ],
        featured: true,
    },
    {
        slug: 'visibility',
        title: 'Live visibility — see the progress of every project',
        summary: 'A read only view of every project: what is started, in progress, or done.',
        roles: ['admin', 'manager'],
        category: 'projects',
        icon: 'eye',
        minutes: 3,
        keywords: ['live', 'visibility', 'progress', 'overview', 'track', 'status', 'dashboard'],
        intro:
            'Live Visibility is your one stop shop to understand how every project is doing — without opening each project one by one. It is read only, so nothing can be changed by accident.',
        steps: [
            {
                title: 'Open Live Visibility',
                body: 'Click "Live Visibility" in the left hand menu.',
                links: [{ label: 'Open Live Visibility', route: 'manager.visibility' }],
            },
            {
                title: 'Read the numbers at the top',
                body: 'The cards show the totals — how many projects exist, how many are in progress, and how many are completed. This is the big picture at a glance.',
            },
            {
                title: 'Find a project',
                body: 'Use the search or the market filter to find the project you care about.',
            },
            {
                title: 'Click the project to open its live page',
                body: 'One click takes you to a read only page for that project. You will see the project details, the site photo, who is on the job, any issues, where the site is, and every closeout package with its progress.',
            },
            {
                title: 'Understand the package progress',
                body: 'Each package shows its steps (for example Pre Construction, Construction, Post Completion) with clear status: not started, in progress, submitted, or approved. In a few seconds you can see exactly what is happening on the site.',
                tip: 'There are no buttons to press on this page — it is a window into the project, so you can safely share it with anyone.',
            },
        ],
        featured: true,
    },

    /* ------------------------------------------------------------------ */
    /* Closeout packages                                                  */
    /* ------------------------------------------------------------------ */
    {
        slug: 'manager-packages',
        title: 'See the packages your supervisors created',
        summary: 'Find every closeout package for a project and its current status.',
        roles: ['manager'],
        category: 'packages',
        icon: 'folder',
        minutes: 2,
        keywords: ['packages', 'list', 'find package', 'status', 'created'],
        intro:
            'Supervisors record the work on site as closeout packages. Here is how to see them all for one project.',
        steps: [
            {
                title: 'Open the project',
                body: 'Go to Projects and click the project. On the project page you will see a card listing its closeout packages.',
                links: [{ label: 'Go to my projects', route: 'projects.index' }],
            },
            {
                title: 'Understand the statuses',
                body: 'Each package shows its stage: Draft (work in progress), Submitted (waiting for your review), or Approved. In Progress means the supervisor is actively working on it.',
            },
            {
                title: 'Open a package',
                body: 'Click "Open package" to see the full package — every step, photo, and checklist answer. This is where you review the work.',
            },
            {
                title: 'Check the live progress',
                body: 'If you want a quick look without opening the package, use Live Visibility — it shows the same progress on one clean page.',
            },
        ],
    },
    {
        slug: 'supervisor-create-package',
        title: 'Create a package and pick a template',
        summary: 'Start a new closeout package for a project using a ready made template.',
        roles: ['field_supervisor'],
        category: 'packages',
        icon: 'folder',
        minutes: 3,
        keywords: ['create', 'new package', 'template', 'start', 'choose'],
        intro:
            'A closeout package is the photo and checklist record for a project. Creating one takes less than a minute.',
        steps: [
            {
                title: 'Open your project',
                body: 'Go to Projects and click the project you are working on.',
                links: [{ label: 'Go to my projects', route: 'projects.index' }],
            },
            {
                title: 'Open Closeout Packages',
                body: 'On the project page, click "Closeout Packages", then click "Create Package".',
            },
            {
                title: 'Choose a template',
                body: 'A template is a ready made checklist. Pick the one your company uses for this type of job. You can also pick from your favorites if you saved one earlier.',
                links: [
                    { label: 'Browse templates', route: 'templates.selector' },
                    { label: 'See my favorite templates', route: 'favorites.index' },
                ],
            },
            {
                title: 'Give the package a name',
                body: 'Type a short name (the project name usually works well), then click Create.',
            },
            {
                title: 'Start working',
                body: 'The package opens on the first step. You can now upload photos and answer the checklist.',
                tip: 'You can leave at any time — the package is saved as a draft and you can come back later.',
            },
        ],
        featured: true,
    },
    {
        slug: 'supervisor-checklist',
        title: 'Answer the checklist',
        summary: 'Answer the yes/no questions for each step — it only takes a minute.',
        roles: ['field_supervisor'],
        category: 'packages',
        icon: 'list',
        minutes: 2,
        keywords: ['checklist', 'questions', 'yes', 'no', 'answers', 'required'],
        intro:
            'Each step has a short checklist that proves the work was checked on site. It is just a few taps.',
        steps: [
            {
                title: 'Open the step',
                body: 'Open your package and click the step you are working on (for example, Construction).',
            },
            {
                title: 'Go to the Checklist tab',
                body: 'At the top of the step you will see tabs — click "Checklist".',
            },
            {
                title: 'Answer each question',
                body: 'Tap Yes or No for every question. Answered questions turn green so you can see your progress at a glance.',
                tip: 'Some items are marked as required — those must be answered before the step can be completed.',
            },
            {
                title: 'Save your answers',
                body: 'Your answers are saved when you save the draft, so nothing is lost if you close the page.',
            },
        ],
    },
    {
        slug: 'supervisor-save-complete',
        title: 'Save drafts and complete steps',
        summary: 'Keep your work safe as you go, and mark a step done when it is finished.',
        roles: ['field_supervisor'],
        category: 'packages',
        icon: 'check',
        minutes: 3,
        keywords: ['draft', 'save', 'complete', 'step', 'next', 'finish'],
        intro:
            'You work on the package one step at a time. Saving keeps your photos and answers safe; completing a step moves you forward.',
        steps: [
            {
                title: 'Save your draft any time',
                body: 'Click "Save draft" whenever you want to keep your work. Your photos and checklist answers are saved, and you can close the page safely.',
            },
            {
                title: 'Complete the step',
                body: 'When the step is finished — photos uploaded and checklist answered — click "Complete step". The step is marked done and you move to the next one.',
            },
            {
                title: 'Follow the steps in order',
                body: 'The steps appear in order (for example Pre Construction, Construction, Post Completion). Completed steps show a green check so you always know where you are.',
            },
            {
                title: 'Come back any time',
                body: 'If you leave partway through, the package stays as a draft. Open it again and continue where you stopped.',
            },
        ],
    },
    {
        slug: 'supervisor-submit',
        title: 'Submit your package for review',
        summary: 'Send the finished package to your manager when every step is done.',
        roles: ['field_supervisor'],
        category: 'packages',
        icon: 'send',
        minutes: 3,
        keywords: ['submit', 'send', 'review', 'finish', 'hand in'],
        intro:
            'When all steps are complete, you send the package to your manager for review. After that, the manager checks every photo and answer.',
        steps: [
            {
                title: 'Finish every step',
                body: 'Make sure each step is completed — all required photos uploaded and all checklist questions answered.',
            },
            {
                title: 'Click "Submit package"',
                body: 'The button is at the top of the package. A message asks you to confirm — click Submit.',
            },
            {
                title: 'What happens next',
                body: 'Your manager gets notified and reviews the package. You cannot change a submitted package until the manager sends something back to you.',
            },
            {
                title: 'If the manager asks for changes',
                body: 'If a photo is flagged as blurry or a replacement is requested, you will see it marked on the step. Fix only the flagged photos, then resubmit. The guide "Fix photos your manager asked to replace" walks you through it.',
            },
            {
                title: 'Get notified',
                body: 'You will be notified when your manager approves the package or asks for changes.',
            },
        ],
        related: ['replacement-photos', 'supervisor-upload-photos'],
    },
    {
        slug: 'replacement-photos',
        title: 'Fix photos your manager asked to replace',
        summary: 'See flagged photos, take a better one, and replace it in seconds.',
        roles: ['field_supervisor'],
        category: 'packages',
        icon: 'camera',
        minutes: 2,
        keywords: ['replacement', 'flagged', 'blurry', 'reject', 'fix photo', 'retake'],
        intro:
            'Sometimes a photo is blurry or not quite right. Your manager marks it, and you simply take a better photo and replace it. Only the flagged photo needs fixing — the rest stays untouched.',
        steps: [
            {
                title: 'Open the package',
                body: 'Open the package your manager reviewed. Steps with flagged photos show a clear warning.',
            },
            {
                title: 'Find the flagged photo',
                body: 'In the step, the flagged photo shows a red or amber badge with the reason — for example "Blurry" or "Replacement requested". Click the photo to read your manager\'s note.',
            },
            {
                title: 'Take a new photo',
                body: 'Click "Replace" on that photo, then take or choose a better one. Wait for the upload to finish.',
            },
            {
                title: 'Resubmit when done',
                body: 'Once every flagged photo is replaced, click "Submit" again to send the package back for review.',
            },
        ],
        featured: true,
    },

    /* ------------------------------------------------------------------ */
    /* Photos                                                             */
    /* ------------------------------------------------------------------ */
    {
        slug: 'supervisor-upload-photos',
        title: 'Upload photos for a step',
        summary: 'Add one photo or a hundred — they upload one by one, safely.',
        roles: ['field_supervisor'],
        category: 'photos',
        icon: 'camera',
        minutes: 3,
        keywords: ['upload', 'photos', 'pictures', 'images', 'add', 'drag', 'mobile'],
        intro:
            'Photos are the heart of the package. You can upload one photo or many at once — the screen shows you the progress of every single upload.',
        steps: [
            {
                title: 'Open the step',
                body: 'Open your package and click the step you want to photograph (for example, Construction).',
            },
            {
                title: 'Go to the Photos area',
                body: 'The step shows the photo categories from your template. Click "Add photos" on the category you are working on.',
            },
            {
                title: 'Pick or drag your photos',
                body: 'Choose photos from your phone or computer. You can pick 1 photo, 10, or even 100 at once — there is no limit. On a computer you can also drag a whole group of photos in.',
            },
            {
                title: 'Watch the upload progress',
                body: 'Each photo uploads one by one and shows a green check when it is done. You can keep working while the rest finish.',
                warning: 'Please keep the page open until the uploads finish. If you close it too early, the remaining photos will not be saved.',
            },
            {
                title: 'Add a note to a photo (optional)',
                body: 'If a photo needs an explanation, click it and add a short note. Notes are optional — photos are fine without them.',
            },
            {
                title: 'Save your work',
                body: 'Click "Save draft" to keep everything. You can add more photos later if you need to.',
            },
        ],
        featured: true,
    },
    {
        slug: 'supervisor-download-image',
        title: 'Download an image you uploaded',
        summary: 'Save any photo from the package to your phone or computer.',
        roles: ['field_supervisor'],
        category: 'photos',
        icon: 'download',
        minutes: 1,
        keywords: ['download', 'save photo', 'export image', 'get my photo back'],
        intro:
            'Every photo you upload stays in the package. You can download any of them back to your device at any time.',
        steps: [
            {
                title: 'Open the package',
                body: 'Open the package and go to the step where the photo is.',
            },
            {
                title: 'Open the photo',
                body: 'Click the photo you want. It opens in a larger view.',
            },
            {
                title: 'Click the download button',
                body: 'The download icon saves the photo to your phone or computer. On a phone it appears in your gallery or downloads folder.',
            },
        ],
    },

    /* ------------------------------------------------------------------ */
    /* Review & approval (manager)                                        */
    /* ------------------------------------------------------------------ */
    {
        slug: 'review-package',
        title: 'Review a package your supervisor submitted',
        summary: 'Check every photo and answer, approve or flag, and finish with final approval.',
        roles: ['admin', 'manager'],
        category: 'review',
        icon: 'clipboard',
        minutes: 4,
        keywords: ['review', 'approve', 'flag', 'blurry', 'replacement', 'notes', 'checklist', 'verify'],
        intro:
            'When a supervisor submits a package, it is your job to check the work. You look at every photo, every answer, and decide: approve it, or ask for a fix. Everything happens on one screen.',
        steps: [
            {
                title: 'Open the submitted package',
                body: 'You will be notified when a package is submitted. Open it from the project page and click "Review".',
            },
            {
                title: 'Go step by step',
                body: 'The package is reviewed in order. Each step shows its photos and checklist answers side by side, so you check everything in one place.',
            },
            {
                title: 'Approve a photo',
                body: 'If a photo is good, click the check mark. The photo is marked approved.',
            },
            {
                title: 'Flag a blurry photo',
                body: 'If a photo is blurry or not good enough, click the flag. Add a short note so the supervisor knows what to fix.',
            },
            {
                title: 'Request a replacement',
                body: 'If the photo needs to be retaken, click "Request replacement". The supervisor will replace only that photo.',
            },
            {
                title: 'Check the checklist answers',
                body: 'Review the yes/no answers for each step. If an answer is wrong, use the note box to explain what to change.',
            },
            {
                title: 'Use the "New replacement" filter',
                body: 'After the supervisor replaces photos, use the filter to jump straight to the new ones — you see exactly which images are waiting for your approval.',
            },
            {
                title: 'Final approval',
                body: 'When every photo is approved and every answer is correct, click "Final approval". The package is officially approved and can produce the final PDF.',
            },
        ],
        faqs: [
            {
                question: 'Can I change my mind after approving a photo?',
                answer: 'No. Once the package is finally approved, photos stay approved so the record stays trustworthy. Be sure everything is right before you click Final approval.',
            },
        ],
        featured: true,
    },

    /* ------------------------------------------------------------------ */
    /* PDFs & emails                                                      */
    /* ------------------------------------------------------------------ */
    {
        slug: 'pdfs',
        title: 'Create and send progress & final PDFs',
        summary: 'Make a PDF report, email it to the client, and download a copy.',
        roles: ['admin', 'manager'],
        category: 'pdfs',
        icon: 'file',
        minutes: 4,
        keywords: ['pdf', 'report', 'progress', 'final', 'email', 'send', 'download', 'verizon', 'client'],
        intro:
            'PDF reports are the clean, printable version of the package — ready to send to the client. You can create one while the work is in progress (progress PDF) and another after final approval (final PDF).',
        steps: [
            {
                title: 'Open the package',
                body: 'Open the package you want to report on.',
            },
            {
                title: 'Create a progress PDF (during the work)',
                body: 'Click "Generate progress PDF". The report is built automatically — you will see a notification when it is ready.',
            },
            {
                title: 'Send the progress PDF to the client',
                body: 'Click "Send to client". The PDF is emailed to the client address on file, and the email is recorded in the audit log.',
            },
            {
                title: 'Download a copy of the progress PDF',
                body: 'Click the download button to save your own copy to your computer.',
            },
            {
                title: 'Create the final PDF (after approval)',
                body: 'After the package receives final approval, click "Generate final PDF". This is the official report of the completed work.',
            },
            {
                title: 'Send the final PDF and download it',
                body: 'Use "Send to client" to email the final PDF to Verizon, and download a copy for your records with the download button.',
            },
            {
                title: 'Check what was sent',
                body: 'Open "Email Audit Logs" from the menu to see every report email, who it went to, and whether it was delivered.',
            },
        ],
        related: ['email-logs'],
        featured: true,
    },
    {
        slug: 'supervisor-download-pdf',
        title: 'Download the PDF of an approved package',
        summary: 'Get your own copy of the final report once the package is approved.',
        roles: ['field_supervisor'],
        category: 'pdfs',
        icon: 'file',
        minutes: 1,
        keywords: ['pdf', 'download', 'report', 'final', 'approved'],
        intro:
            'Once your manager approves the package, the final PDF report becomes available. You can download your own copy any time.',
        steps: [
            {
                title: 'Open the approved package',
                body: 'Open the package — it is marked Approved.',
            },
            {
                title: 'Click the PDF download button',
                body: 'The download button appears when the PDF is ready. It saves the report to your phone or computer.',
                tip: 'Only approved packages can produce a final PDF. If you cannot see the button, the package may still be in review.',
            },
        ],
    },

    /* ------------------------------------------------------------------ */
    /* Admin & management                                                 */
    /* ------------------------------------------------------------------ */
    {
        slug: 'admin-overview',
        title: 'See the big picture (dashboard)',
        summary: 'Your dashboard shows the health of the whole business at a glance.',
        roles: ['admin'],
        category: 'admin',
        icon: 'dashboard',
        minutes: 2,
        keywords: ['overview', 'dashboard', 'stats', 'numbers', 'kpi', 'home'],
        intro:
            'The dashboard is your home screen. It shows the important numbers — how many projects exist, what is in progress, and what is completed — so you always know the state of the business.',
        steps: [
            {
                title: 'Open the Dashboard',
                body: 'Click "Dashboard" in the left hand menu.',
                links: [{ label: 'Go to the dashboard', route: 'dashboard' }],
            },
            {
                title: 'Read the numbers',
                body: 'The cards at the top show the totals: projects, work in progress, completed work, and more. Clicking a card filters what you see below.',
            },
            {
                title: 'Check the recent activity',
                body: 'The Activity tab shows what has happened recently across the whole app — who created a project, who submitted a package, and when. This is your window into the day to day.',
                links: [{ label: 'Open the activity log', route: 'dashboard', query: { tab: 'activity' } }],
            },
            {
                title: 'Drill into Live Visibility',
                body: 'For a project by project view of progress, open Live Visibility from the menu.',
            },
        ],
        featured: true,
    },
    {
        slug: 'admin-users',
        title: 'Manage users and their roles',
        summary: 'Add new users, choose their role, and keep accounts safe.',
        roles: ['admin'],
        category: 'admin',
        icon: 'users',
        minutes: 3,
        keywords: ['users', 'accounts', 'roles', 'add user', 'password', 'deactivate'],
        intro:
            'Users are the people who sign in to COP. Each user gets a role — Admin, Manager, Field Supervisor, or Customer — and the role decides what they can see and do.',
        steps: [
            {
                title: 'Open Users',
                body: 'Click "Users" in the left hand menu.',
                links: [{ label: 'Go to Users', route: 'admin.users.index' }],
            },
            {
                title: 'Add a new user',
                body: 'Click "Add user" and fill in their name and email, choose their role, and pick their market. The user receives sign in details.',
            },
            {
                title: 'Choose the right role',
                body: 'Admins manage everything. Managers review work and manage projects. Supervisors upload photos and answer checklists. Customers can only see their projects in the portal.',
            },
            {
                title: 'Change a user\'s role',
                body: 'Open the user and pick a different role, then save. Their menu changes to match their new role.',
            },
            {
                title: 'Deactivate an account',
                body: 'If someone leaves the company, open the user and deactivate them. They can no longer sign in, but their work history is kept.',
            },
            {
                title: 'Reset a password',
                body: 'If a user forgets their password, open their account and use "Change password" to set a new one.',
            },
        ],
    },
    {
        slug: 'admin-markets',
        title: 'Manage markets (Ohio, Texas, …)',
        summary: 'Add regions, turn them on or off, and control who works in each.',
        roles: ['admin'],
        category: 'admin',
        icon: 'map',
        minutes: 2,
        keywords: ['markets', 'regions', 'ohio', 'texas', 'locations'],
        intro:
            'A market is a region the business works in, like Ohio or Texas. Projects and users are organised by market, so everyone sees the right slice of the business.',
        steps: [
            {
                title: 'Open Markets',
                body: 'Click "Markets" in the left hand menu.',
                links: [{ label: 'Go to Markets', route: 'admin.markets.index' }],
            },
            {
                title: 'Add or edit a market',
                body: 'Click "Add market" to create a new region, or open an existing one to rename it.',
            },
            {
                title: 'Turn a market on or off',
                body: 'Deactivate a market that is no longer used. Existing projects stay safe — the market just stops appearing for new work.',
            },
            {
                title: 'Attach users to a market',
                body: 'Inside a market, add or remove users. This controls which market\'s projects they can work on.',
            },
        ],
    },
    {
        slug: 'admin-templates',
        title: 'Build templates (the checklists your supervisors follow)',
        summary: 'Create ready made checklists so every project is recorded the same way.',
        roles: ['admin'],
        category: 'admin',
        icon: 'list',
        minutes: 4,
        keywords: ['templates', 'checklist', 'sections', 'categories', 'builder', 'questions'],
        intro:
            'Templates are the backbone of consistent work. A template defines the steps of a package (Pre Construction, Construction, …), the photo categories, and the checklist questions. Supervisors pick a template when they create a package.',
        steps: [
            {
                title: 'Open Templates',
                body: 'Click "Templates" in the left hand menu.',
                links: [{ label: 'Go to Templates', route: 'admin.templates.index' }],
            },
            {
                title: 'Create a template',
                body: 'Click "Create template", give it a clear name (for example "Ohio Standard"), and save.',
            },
            {
                title: 'Add sections (the steps)',
                body: 'Sections become the steps of the package. Add one for each phase of the job, in order.',
            },
            {
                title: 'Add photo categories',
                body: 'Inside each section, add the photo categories — for example "Foundation" or "Roof". Supervisors upload photos into these groups.',
            },
            {
                title: 'Add checklist questions',
                body: 'For each section, add the yes/no questions supervisors must answer. Mark important ones as required.',
            },
            {
                title: 'Reorder with drag and drop',
                body: 'Drag sections or questions into the right order. The order you set is the order your team follows.',
            },
            {
                title: 'Supervisors see it immediately',
                body: 'As soon as a template is saved, it appears in the package creation flow for supervisors — no extra steps needed.',
            },
        ],
    },
    {
        slug: 'admin-customer-access',
        title: 'Give customers access to projects',
        summary: 'Let a customer see their projects in the portal — and revoke it any time.',
        roles: ['admin'],
        category: 'admin',
        icon: 'shield',
        minutes: 2,
        keywords: ['customer', 'access', 'portal', 'grant', 'revoke', 'permissions'],
        intro:
            'Customers see only what you give them. You choose which projects each customer can view in their portal.',
        steps: [
            {
                title: 'Open Customer Access',
                body: 'Click "Customer Access" in the left hand menu.',
                links: [{ label: 'Go to Customer Access', route: 'admin.customers.index' }],
            },
            {
                title: 'Choose the customer',
                body: 'Pick the customer account you want to give access to.',
            },
            {
                title: 'Choose the projects',
                body: 'Select one project or several, then grant access. The customer now sees those projects in their portal.',
            },
            {
                title: 'Revoke access any time',
                body: 'Remove a project from a customer at any point — their portal updates immediately.',
            },
        ],
    },
    {
        slug: 'admin-deleted-packages',
        title: 'Recover or permanently delete packages',
        summary: 'Deleted packages go to a recovery bin before they are gone for good.',
        roles: ['admin'],
        category: 'admin',
        icon: 'trash',
        minutes: 2,
        keywords: ['deleted', 'restore', 'purge', 'trash', 'recover', 'delete'],
        intro:
            'When someone deletes a package, it is not gone forever — it moves to a recovery list first. You decide whether to bring it back or remove it completely.',
        steps: [
            {
                title: 'Open Deleted Packages',
                body: 'Click "Deleted Packages" in the left hand menu.',
                links: [{ label: 'Go to Deleted Packages', route: 'admin.packages.deleted' }],
            },
            {
                title: 'Restore a package',
                body: 'If a package was deleted by mistake, click "Restore". It goes back to the project exactly as it was.',
            },
            {
                title: 'Delete a package forever',
                body: 'If you are sure a package is not needed, click "Purge" to remove it permanently. This cannot be undone.',
                warning: 'Purging is permanent. Only do this when you are certain the package is no longer needed.',
            },
        ],
    },
    {
        slug: 'activity-logs',
        title: 'See what is happening (activity logs)',
        summary: 'A timeline of every important action — who did what, and when.',
        roles: ['admin', 'manager'],
        category: 'admin',
        icon: 'clock',
        minutes: 2,
        keywords: ['activity', 'logs', 'audit', 'history', 'who did what', 'timeline'],
        intro:
            'The activity log is a running timeline of the whole system: who created a project, who submitted a package, who approved a photo. It is the best way to keep track of everything.',
        steps: [
            {
                title: 'Open the dashboard',
                body: 'Click "Dashboard" in the left hand menu.',
                links: [{ label: 'Go to the dashboard', route: 'dashboard' }],
            },
            {
                title: 'Open the Activity Logs tab',
                body: 'Click "Activity Logs" under Dashboard (or the tab on the dashboard page).',
                links: [{ label: 'Open Activity Logs', route: 'dashboard', query: { tab: 'activity' } }],
            },
            {
                title: 'Read the timeline',
                body: 'Each entry shows who did what and when. Use it to answer questions like "when was this package submitted?" or "who changed this project?"',
            },
        ],
    },
    {
        slug: 'email-logs',
        title: 'Check the emails we sent (audit log)',
        summary: 'See every report email, who it went to, and whether it was delivered.',
        roles: ['admin', 'manager'],
        category: 'admin',
        icon: 'mail',
        minutes: 2,
        keywords: ['email', 'logs', 'audit', 'sent', 'delivered', 'failed'],
        intro:
            'Every report email — progress PDFs, final PDFs, notifications — is recorded in the email log. It shows what was sent, to whom, and whether it arrived.',
        steps: [
            {
                title: 'Open Email Audit Logs',
                body: 'Click "Email Audit Logs" in the left hand menu.',
                links: [{ label: 'Go to Email Audit Logs', route: 'manager.email-logs.manager' }],
            },
            {
                title: 'Check the status',
                body: 'Each email shows whether it was delivered, is still sending, or failed. If one failed, you can retry or resend the report from the package.',
            },
            {
                title: 'Find a specific email',
                body: 'Use the search to find an email by recipient or project, or filter by status.',
            },
        ],
    },
    {
        slug: 'manager-dashboard',
        title: 'Your manager dashboard',
        summary: 'A quick look at your numbers and your team\'s recent activity.',
        roles: ['manager'],
        category: 'admin',
        icon: 'dashboard',
        minutes: 2,
        keywords: ['overview', 'dashboard', 'stats', 'numbers', 'activity', 'home'],
        intro:
            'The dashboard gives you a quick snapshot of your world — your projects, your team\'s activity, and what needs your attention.',
        steps: [
            {
                title: 'Open the Dashboard',
                body: 'Click "Dashboard" in the left hand menu.',
                links: [{ label: 'Go to the dashboard', route: 'dashboard' }],
            },
            {
                title: 'See your numbers',
                body: 'The cards show your totals — for example your projects and packages in progress. Click a card to see the details below.',
            },
            {
                title: 'Check your team\'s activity',
                body: 'The Activity tab shows what your supervisors have been doing — who submitted a package, who uploaded photos, and when.',
                links: [{ label: 'Open Activity Logs', route: 'dashboard', query: { tab: 'activity' } }],
            },
        ],
    },

    /* ------------------------------------------------------------------ */
    /* Chat                                                               */
    /* ------------------------------------------------------------------ */
    {
        slug: 'chat',
        title: 'Chat with your team',
        summary: 'Send a message about a project — no email needed.',
        roles: ['manager', 'field_supervisor'],
        category: 'communication',
        icon: 'chat',
        minutes: 2,
        keywords: ['chat', 'message', 'talk', 'contact', 'ask'],
        intro:
            'Every project has a chat. It is the simplest way to ask a question or share a note without leaving the app or writing an email.',
        steps: [
            {
                title: 'Open a project\'s chat',
                body: 'Go to the project, then open Chat. You can also open the Chat screen and pick the project from the list.',
                links: [{ label: 'Open my chats', route: 'chat.projects' }],
            },
            {
                title: 'Type and send',
                body: 'Type your message at the bottom and press send. The other side sees it right away.',
            },
            {
                title: 'Watch for unread messages',
                body: 'A red badge on the Support/Chat area shows how many messages are waiting for you. Opening the chat marks them as read.',
            },
        ],
    },

    /* ------------------------------------------------------------------ */
    /* Customer portal                                                    */
    /* ------------------------------------------------------------------ */
    {
        slug: 'customer-portal',
        title: 'Welcome to your customer portal',
        summary: 'One place to see your projects, their progress, and their reports.',
        roles: ['customer'],
        category: 'customer',
        icon: 'home',
        minutes: 2,
        keywords: ['welcome', 'portal', 'start', 'first time', 'customer'],
        intro:
            'Welcome! The customer portal is your window into your projects. You can see progress, look at photos, and download the PDF reports — all in one place, with no technical knowledge needed.',
        steps: [
            {
                title: 'Sign in',
                body: 'Open the link you received by email and sign in. If you have trouble, contact the person who invited you.',
            },
            {
                title: 'See your projects',
                body: 'The portal home lists every project you have access to, with a progress bar so you can see how each one is doing.',
                links: [{ label: 'Open my portal', route: 'portal.index' }],
            },
            {
                title: 'Open a project',
                body: 'Click a project to see its details and the closeout packages created for it.',
            },
            {
                title: 'View photos and PDFs',
                body: 'Inside a package you can browse the site photos step by step and open or download the PDF reports.',
            },
            {
                title: 'This is read only',
                body: 'You can look at everything, but you cannot change anything — the portal is your window into the work.',
            },
        ],
        featured: true,
    },
    {
        slug: 'customer-projects',
        title: 'See your projects and their progress',
        summary: 'Understand at a glance how each of your projects is doing.',
        roles: ['customer'],
        category: 'customer',
        icon: 'building',
        minutes: 2,
        keywords: ['projects', 'progress', 'status', 'percentage'],
        intro:
            'The portal home shows your projects with a clear progress bar. In seconds you know where each project stands.',
        steps: [
            {
                title: 'Open the portal',
                body: 'After signing in you land on the portal home, which lists your projects.',
                links: [{ label: 'Open my portal', route: 'portal.index' }],
            },
            {
                title: 'Read the progress bar',
                body: 'Each project shows how complete it is — for example 60%. Underneath, the status tells you if work is not started, in progress, or completed.',
            },
            {
                title: 'Open a project',
                body: 'Click a project to see its details, the people working on it, and its closeout packages.',
            },
        ],
    },
    {
        slug: 'customer-packages',
        title: 'Open packages and view photos',
        summary: 'Browse the site photos and checklist answers for each project.',
        roles: ['customer'],
        category: 'customer',
        icon: 'folder',
        minutes: 2,
        keywords: ['packages', 'photos', 'pictures', 'steps', 'view'],
        intro:
            'A closeout package is the photo record of the work. You can browse it step by step, exactly like the team recorded it.',
        steps: [
            {
                title: 'Open your project',
                body: 'From the portal home, click the project you are interested in.',
                links: [{ label: 'Open my portal', route: 'portal.index' }],
            },
            {
                title: 'Open a package',
                body: 'Click a closeout package to open it. Each step of the work is shown in order.',
            },
            {
                title: 'Browse the photos',
                body: 'Scroll through the photos for each step. Click any photo to see it larger, with any notes the team added.',
            },
        ],
    },
    {
        slug: 'customer-pdf',
        title: 'View and download PDF reports',
        summary: 'Open or save the official report for any approved package.',
        roles: ['customer'],
        category: 'customer',
        icon: 'file',
        minutes: 1,
        keywords: ['pdf', 'report', 'download', 'view', 'print'],
        intro:
            'The PDF report is the clean, official version of the package — ready to view, print, or save.',
        steps: [
            {
                title: 'Open the package',
                body: 'Open the project and click the package you want the report for.',
                links: [{ label: 'Open my portal', route: 'portal.index' }],
            },
            {
                title: 'Open the PDF',
                body: 'Click "View PDF". The report opens in a new window with every photo and checklist answer.',
            },
            {
                title: 'Download or print',
                body: 'Use the download icon to save the PDF to your device, or print it directly from the viewer.',
            },
        ],
    },
];

/* ---------------------------------------------------------------------- */
/* Lookup helpers                                                         */
/* ---------------------------------------------------------------------- */

export function getArticleBySlug(slug: string): HelpArticle | undefined {
    return HELP_ARTICLES.find((article) => article.slug === slug);
}

export function getCategoryById(id: HelpCategoryId): HelpCategory | undefined {
    return HELP_CATEGORIES.find((category) => category.id === id);
}

export function getRelatedArticles(article: HelpArticle): HelpArticle[] {
    const bySlug = (article.related ?? [])
        .map(getArticleBySlug)
        .filter((item): item is HelpArticle => item !== undefined);
    const sameCategory = HELP_ARTICLES.filter(
        (candidate) =>
            candidate.slug !== article.slug &&
            candidate.category === article.category &&
            candidate.roles.some((role) => article.roles.includes(role)),
    );
    const seen = new Set<string>(bySlug.map((item) => item.slug));
    return [...bySlug, ...sameCategory.filter((item) => !seen.has(item.slug))].slice(0, 3);
}

/**
 * Resolve a HelpLink into a real URL using Ziggy route names.
 * Query-string values (like the activity tab) are appended explicitly.
 */
export function resolveHelpLink(link: HelpLink): string {
    const url = route(link.route, link.params ?? {});
    if (link.query) {
        const queryString = new URLSearchParams(link.query).toString();
        return `${url}${url.includes('?') ? '&' : '?'}${queryString}`;
    }
    return url;
}

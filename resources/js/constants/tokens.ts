/**
 * Single Source of Truth — Design Tokens & UI System Specifications
 * Reference: Phase 1 of Frontend Implementation Blueprint
 */

export const SPACING = {
    SYSTEM_BASE: 8,
    SPACING_1: '4px',    // 0.5 * 8
    SPACING_2: '8px',    // 1 * 8
    SPACING_3: '12px',   // 1.5 * 8
    SPACING_4: '16px',   // 2 * 8
    SPACING_6: '24px',   // 3 * 8
    SPACING_8: '32px',   // 4 * 8
    SPACING_10: '40px',  // 5 * 8
    SPACING_12: '48px',  // 6 * 8
    SPACING_16: '64px',  // 8 * 8
    SPACING_20: '80px',  // 10 * 8
} as const;

export const LAYOUT = {
    MAX_PAGE_WIDTH: 'max-w-7xl', // 1280px
    CONTENT_WIDTH: 'max-w-4xl',   // 896px
    SIDEBAR_EXPANDED_WIDTH: 'w-64', // 256px
    SIDEBAR_COLLAPSED_WIDTH: 'w-16', // 64px
    HEADER_HEIGHT: 'h-16',       // 64px
    TOUCH_TARGET_MIN: 44,        // 44px minimum for iOS / mobile
} as const;

export const BREAKPOINTS = {
    SM: 640,   // Mobile Landscape
    MD: 768,   // Tablet Portrait
    LG: 1024,  // Tablet Landscape / Small Laptop
    XL: 1280,  // Desktop
    XXL: 1536, // Wide Desktop
} as const;

export const RADIUS = {
    SM: '4px',
    MD: '6px',
    LG: '8px',
    XL: '12px',
    XXL: '16px',
    FULL: '9999px',
} as const;

export const TYPOGRAPHY_CLASSES = {
    DISPLAY: 'text-display',
    H1: 'text-h1',
    H2: 'text-h2',
    H3: 'text-h3',
    H4: 'text-h4',
    SUBTITLE: 'text-subtitle',
    BODY: 'text-body',
    BODY_SM: 'text-body-sm',
    CAPTION: 'text-caption',
    LABEL: 'text-label',
    BADGE: 'text-badge',
    HELPER: 'text-helper',
    ERROR: 'text-error',
    SUCCESS: 'text-success',
} as const;

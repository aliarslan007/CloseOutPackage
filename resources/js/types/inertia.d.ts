export interface User {
    id: number;
    name: string;
    email: string;
    company?: string | null;
    phone?: string | null;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    profile_photo_url?: string | null;
    roles: string[];
    permissions: string[];
}

export interface Market {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
}

export interface AuthState {
    user: User;
    roles: string[];
    permissions: string[];
    markets: Market[];
}

export interface FlashState {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
}

export interface SharedProps extends Record<string, unknown> {
    auth: AuthState;
    flash: FlashState;
    errors: Record<string, string>;
    /** Backend-owned cap on a review message (query or answer), in characters. */
    feedbackMessageLimit: number;
    /** Backend-owned window, in minutes, in which an author may correct a message. */
    feedbackEditWindowMinutes: number;
    app?: {
        version: string;
    };
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T &
    SharedProps;

/**
 * Fallback for the message-length rule the backend owns. The real limit
 * arrives as a shared Inertia prop (`feedbackMessageLimit`), so this only
 * covers a page rendered outside the normal middleware chain or an old
 * cached build. Keep in sync with `EntityFeedbackNote::MAX_BODY_LENGTH` (3000).
 */
export const DEFAULT_FEEDBACK_MESSAGE_LIMIT = 3000;

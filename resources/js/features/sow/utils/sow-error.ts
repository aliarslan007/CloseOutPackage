import axios from 'axios';

/** Shared axios → toast message extractor (was copy-pasted in 4 SOW files). */
export function sowErrorMessage(err: unknown, fallback: string): string {
    if (axios.isAxiosError(err)) {
        return (err.response?.data?.message as string | undefined) ?? fallback;
    }
    return fallback;
}

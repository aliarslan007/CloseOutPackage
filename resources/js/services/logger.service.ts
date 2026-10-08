export class LoggerService {
    public static error(context: string, error: unknown, details?: Record<string, unknown>): void {
        console.error(`[${context}]`, error, details);
    }

    public static warn(context: string, message: string, details?: Record<string, unknown>): void {
        console.warn(`[${context}]`, message, details);
    }
}

export default LoggerService;

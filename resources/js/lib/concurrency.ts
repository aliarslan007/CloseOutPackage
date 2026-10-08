/**
 * Run an async worker over items with a bounded number of in-flight promises,
 * preserving result order. Failed workers reject the whole run — wrap errors
 * inside the worker when partial success is expected.
 */
export async function runWithConcurrency<T, U>(
    items: readonly T[],
    concurrency: number,
    worker: (item: T, index: number) => Promise<U>,
): Promise<U[]> {
    const results = new Array<U>(items.length);
    let next = 0;

    const runner = async (): Promise<void> => {
        while (next < items.length) {
            const index = next;
            next += 1;
            results[index] = await worker(items[index], index);
        }
    };

    const lanes = Array.from({ length: Math.max(1, Math.min(concurrency, items.length)) }, () =>
        runner(),
    );

    await Promise.all(lanes);

    return results;
}

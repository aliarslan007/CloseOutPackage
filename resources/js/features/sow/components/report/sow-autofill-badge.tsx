/** Marks a field whose value was pulled from the selected project. */
export function SowAutofillBadge({ projectName }: Readonly<{ projectName?: string | null }>) {
    return (
        <span
            title={
                projectName
                    ? `Filled automatically from ${projectName}`
                    : 'Filled automatically from the selected project'
            }
            className="inline-flex shrink-0 items-center rounded border border-border bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
        >
            From project
        </span>
    );
}

/**
 * Splits a full "First Last" name into [firstName, lastName].
 * Names with more than two words keep the remainder in the last name,
 * mirroring how the backend composes the single `name` column.
 */
export function splitName(name: string): [string, string] {
    const parts = name.trim().split(' ');
    return [parts[0] ?? '', parts.slice(1).join(' ')];
}

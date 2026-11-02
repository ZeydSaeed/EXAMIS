export const GAP = '{فراغ}';

export type MessageContact = {
    name: string | null;
    number: number | null;
    branch: string | null;
    specialization: string | null;
    phone: string | null;
};

export function gapCount(body: string): number {
    return body.split(GAP).length - 1;
}

/**
 * Fills a template. A line whose gaps were all left empty is dropped, so a
 * list with room for three items can hold one; while nothing is filled in the
 * gaps stay visible, and the server refuses to send them.
 */
export function composeMessage(
    body: string,
    contact: MessageContact,
    gaps: string[],
): string {
    const anyFilled = gaps.some((gap) => gap.trim() !== '');
    let index = 0;

    return body
        .split('\n')
        .flatMap((line) => {
            const pieces = line.split(GAP);

            if (pieces.length === 1) {
                return [line];
            }

            const values = pieces
                .slice(1)
                .map(() => (gaps[index++] ?? '').trim());

            if (anyFilled && values.every((value) => value === '')) {
                return [];
            }

            return [
                pieces.reduce(
                    (text, piece, position) =>
                        text +
                        (position > 0 ? values[position - 1] || GAP : '') +
                        piece,
                    '',
                ),
            ];
        })
        .join('\n')
        .replaceAll('{اسم_الطالب}', contact.name ?? '')
        .replaceAll('{رقم_الطالب}', String(contact.number ?? ''))
        .replaceAll('{الفرع}', contact.branch ?? '')
        .replaceAll('{الاختصاص}', contact.specialization ?? '');
}

/** Opens the staff member's WhatsApp on this student, ready to send. */
export function whatsappUrl(phone: string, message: string): string {
    return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}

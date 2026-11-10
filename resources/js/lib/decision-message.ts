export const GAP = '{فراغ}';

const NUMBERED_LINE = /^(\s*)\d+(\s*[.)-]\s*)/;

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
 * gaps stay visible, and the server refuses to send them. Gaps beyond the
 * template's own follow its last gap line, and a numbered list stays in
 * sequence.
 */
export function composeMessage(
    body: string,
    contact: MessageContact,
    gaps: string[],
): string {
    const lines = body.split('\n');
    const lastGapLine = lines
        .map((line) => line.includes(GAP))
        .lastIndexOf(true);
    const extra = gaps
        .slice(gapCount(body))
        .map((gap) => gap.trim())
        .filter((gap) => gap !== '');
    const anyFilled = gaps.some((gap) => gap.trim() !== '');
    let index = 0;
    let number = 0;

    const renumber = (line: string) =>
        line.replace(
            NUMBERED_LINE,
            (_, indent: string, separator: string) =>
                `${indent}${++number}${separator}`,
        );

    return lines
        .flatMap((line, lineIndex) => {
            const pieces = line.split(GAP);

            if (pieces.length === 1) {
                return [line];
            }

            const values = pieces
                .slice(1)
                .map(() => (gaps[index++] ?? '').trim());
            const keep = !anyFilled || values.some((value) => value !== '');
            const filled = keep
                ? [
                      renumber(
                          pieces.reduce(
                              (text, piece, position) =>
                                  text +
                                  (position > 0
                                      ? values[position - 1] || GAP
                                      : '') +
                                  piece,
                              '',
                          ),
                      ),
                  ]
                : [];

            return lineIndex === lastGapLine
                ? [
                      ...filled,
                      ...extra.map((gap) => renumber(`${pieces[0]}${gap}`)),
                  ]
                : filled;
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

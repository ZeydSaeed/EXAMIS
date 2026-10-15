import { Fragment } from 'react';

/** Same folds as the staff search on the server (ApplicationController). */
const FOLDS: Record<string, string> = {
    أ: 'ا',
    إ: 'ا',
    آ: 'ا',
    ى: 'ي',
    ة: 'ه',
};

const IGNORED = /[ً-ٰٟـ]/;

/** Folded text plus, for each folded character, its index in the original. */
function fold(text: string): { folded: string; origin: number[] } {
    let folded = '';
    const origin: number[] = [];

    Array.from(text).forEach((char, index) => {
        if (IGNORED.test(char)) {
            return;
        }

        folded += (FOLDS[char] ?? char).toLocaleLowerCase();
        origin.push(index);
    });

    return { folded, origin };
}

/**
 * Marks every word of `query` found in `text`, ignoring case, Arabic letter
 * variants and diacritics.
 */
export function Highlight({
    text,
    query,
}: {
    text: string | null | undefined;
    query: string | null | undefined;
}) {
    if (!text) {
        return <>{text}</>;
    }

    const words = fold(query ?? '')
        .folded.split(/\s+/)
        .filter(Boolean);

    if (words.length === 0) {
        return <>{text}</>;
    }

    const chars = Array.from(text);
    const { folded, origin } = fold(text);
    const marked = chars.map(() => false);

    for (const word of words) {
        for (
            let at = folded.indexOf(word);
            at !== -1;
            at = folded.indexOf(word, at + word.length)
        ) {
            const from = origin[at];
            const to = origin[at + word.length - 1];

            for (let index = from; index <= to; index++) {
                marked[index] = true;
            }
        }
    }

    const parts: { text: string; mark: boolean }[] = [];

    chars.forEach((char, index) => {
        const last = parts[parts.length - 1];

        if (last && last.mark === marked[index]) {
            last.text += char;
        } else {
            parts.push({ text: char, mark: marked[index] });
        }
    });

    return (
        <>
            {parts.map((part, index) =>
                part.mark ? (
                    <mark key={index} className="examis-highlight">
                        {part.text}
                    </mark>
                ) : (
                    <Fragment key={index}>{part.text}</Fragment>
                ),
            )}
        </>
    );
}

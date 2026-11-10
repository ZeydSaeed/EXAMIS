import { useState } from 'react';
import { selectClassName } from '@/components/examis/field';
import { useTrans } from '@/hooks/use-trans';

type Parts = { day: string; month: string; year: string };

const split = (value: string | null): Parts => {
    const [year = '', month = '', day = ''] = value?.split('-') ?? [];

    return { day, month, year };
};

/** Feb 29 stays open while the year is not chosen yet. */
const daysIn = (month: string, year: string) =>
    month ? new Date(Number(year || 2000), Number(month), 0).getDate() : 31;

const pad = (value: number) => String(value).padStart(2, '0');

/**
 * A date as three choices (day, month, year), stored as one `Y-m-d` value.
 * Until all three are chosen the value stays null; the partial choice is
 * kept here so it is not lost. The first choice carries `id`, so the field
 * label and focus land on it.
 */
export function DatePartsInput({
    id,
    value,
    onChange,
    fromYear,
    toYear,
    disabled,
    invalid,
    describedBy,
}: {
    id: string;
    value: string | null;
    onChange: (value: string | null) => void;
    /** Newest year first, down to the oldest. */
    fromYear: number;
    toYear: number;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
}) {
    const { t } = useTrans();
    const [draft, setDraft] = useState<Parts>(() => split(value));
    const parts = value ? split(value) : draft;

    const update = (part: keyof Parts, choice: string) => {
        const next = { ...parts, [part]: choice };

        if (Number(next.day) > daysIn(next.month, next.year)) {
            next.day = '';
        }

        setDraft(next);
        onChange(
            next.day && next.month && next.year
                ? `${next.year}-${next.month}-${next.day}`
                : null,
        );
    };

    const days = Array.from(
        { length: daysIn(parts.month, parts.year) },
        (_, index) => pad(index + 1),
    );
    const months = Array.from({ length: 12 }, (_, index) => pad(index + 1));
    const years = Array.from({ length: fromYear - toYear + 1 }, (_, index) =>
        String(fromYear - index),
    );

    const choice = (
        part: keyof Parts,
        choices: string[],
        controlId: string,
    ) => (
        <select
            id={controlId}
            name={`${id}_${part}`}
            className={selectClassName}
            value={parts[part]}
            disabled={disabled}
            onChange={(event) => update(part, event.target.value)}
            aria-label={t(`fields.${part}`)}
            aria-invalid={invalid ? true : undefined}
            aria-describedby={describedBy}
            data-test={`${id}-${part}`}
        >
            <option value="">{t(`fields.${part}`)}</option>
            {choices.map((option) => (
                <option key={option} value={option}>
                    {Number(option)}
                </option>
            ))}
        </select>
    );

    return (
        <div className="grid grid-cols-3 gap-2">
            {choice('day', days, id)}
            {choice('month', months, `${id}_month`)}
            {choice('year', years, `${id}_year`)}
        </div>
    );
}

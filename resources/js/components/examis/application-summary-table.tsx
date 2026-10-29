import type { ReactNode } from 'react';
import { DataTableFrame } from '@/components/examis/data-table-frame';

type Row = [label: string, value: ReactNode];

type Section = {
    key: string;
    title: string;
    rows: Row[];
    /** e.g. an "Edit" button, shown at the end of the section header. */
    action?: ReactNode;
};

/**
 * Label/value rows (e.g. from `applicationSummary`) as a two-column table in
 * the shared data-table style, labels as row headers. Pass `sections` to
 * group the rows under header rows.
 */
export function ApplicationSummaryTable({
    rows,
    sections,
    caption,
}: {
    rows?: Row[];
    sections?: Section[];
    caption: string;
}) {
    const groups: Section[] = sections ?? [
        { key: 'all', title: '', rows: rows ?? [] },
    ];

    return (
        <DataTableFrame className="examis-data-table--plain">
            <table>
                <caption className="sr-only">{caption}</caption>
                {groups.map((group) => (
                    <tbody key={group.key}>
                        {group.title !== '' && (
                            <tr>
                                <th
                                    scope="colgroup"
                                    colSpan={2}
                                    className="bg-[var(--examis-navy)]/8 text-start dark:bg-white/8"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="font-bold">
                                            {group.title}
                                        </span>
                                        {group.action}
                                    </div>
                                </th>
                            </tr>
                        )}
                        {group.rows.map(([label, value]) => (
                            <tr key={label}>
                                <th
                                    scope="row"
                                    className="w-2/5 bg-muted/60 text-start font-semibold"
                                >
                                    {label}
                                </th>
                                <td className="whitespace-pre-line">
                                    <bdi>
                                        {value === null || value === ''
                                            ? '—'
                                            : value}
                                    </bdi>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                ))}
            </table>
        </DataTableFrame>
    );
}

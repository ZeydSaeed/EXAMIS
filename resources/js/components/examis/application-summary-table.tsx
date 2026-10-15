import { DataTableFrame } from '@/components/examis/data-table-frame';

/**
 * Label/value rows (e.g. from `applicationSummary`) as a two-column table in
 * the shared data-table style, labels as row headers.
 */
export function ApplicationSummaryTable({
    rows,
    caption,
}: {
    rows: [label: string, value: string | number | null][];
    caption: string;
}) {
    return (
        <DataTableFrame className="examis-data-table--plain">
            <table>
                <caption className="sr-only">{caption}</caption>
                <tbody>
                    {rows.map(([label, value]) => (
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
            </table>
        </DataTableFrame>
    );
}

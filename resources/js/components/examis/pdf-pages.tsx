import type { PDFDocumentLoadingTask } from 'pdfjs-dist';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';

/**
 * About 216 dpi: one drawing, for the screen and for paper. Phones that
 * report 2 GB of memory or less get about 144 dpi: an A4 page at 3× needs
 * about 18 MB of canvas and can stall them, and the barcode still reads.
 */
const RENDER_SCALE =
    ((navigator as Navigator & { deviceMemory?: number }).deviceMemory ?? 8) <=
    2
        ? 2
        : 3;

/**
 * Draws every page of a PDF at the full width of the page, one under the
 * other, and prints them when the page prints. Browsers' own PDF viewers
 * cannot be restyled, do not exist on most phones and do not say when a print
 * ends, so the file is rendered here instead. The library is only loaded
 * where this component is used.
 */
export function PdfPages({
    url,
    title,
    onReady,
}: {
    url: string;
    title: string;
    onReady?: () => void;
}) {
    const { t } = useTrans();
    const holder = useRef<HTMLDivElement>(null);
    const notifyReady = useRef(onReady);
    const [state, setState] = useState<'loading' | 'ready' | 'failed'>(
        'loading',
    );
    const [sheets, setSheets] = useState<string[]>([]);

    useEffect(() => {
        notifyReady.current = onReady;
    }, [onReady]);

    useEffect(() => {
        const container = holder.current;
        let cancelled = false;
        let task: PDFDocumentLoadingTask | null = null;
        const sheetUrls: string[] = [];

        const render = async () => {
            // All three start together, so the file is not held up by the code.
            const [
                { getDocument, GlobalWorkerOptions },
                { default: PdfWorker },
                data,
            ] = await Promise.all([
                import('pdfjs-dist'),
                // Built as a plain .js file: many servers do not send .mjs
                // with a type browsers accept for a worker.
                import('pdfjs-dist/build/pdf.worker.min.mjs?worker'),
                fetch(url).then((response) => {
                    if (!response.ok) {
                        throw new Error('The form could not be loaded.');
                    }

                    return response.arrayBuffer();
                }),
            ]);

            if (cancelled || !container) {
                return;
            }

            GlobalWorkerOptions.workerPort ??= new PdfWorker();
            task = getDocument({ data, isEvalSupported: false });

            const document_ = await task.promise;
            const rendered: string[] = [];

            for (let number = 1; number <= document_.numPages; number++) {
                const page = await document_.getPage(number);
                const viewport = page.getViewport({ scale: RENDER_SCALE });
                const canvas = document.createElement('canvas');
                const canvasContext = canvas.getContext('2d');

                if (cancelled || !canvasContext) {
                    return;
                }

                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.className = 'w-full rounded-sm bg-white shadow-sm';
                // A canvas in the page takes on its direction, and pdf.js places
                // every glyph assuming left to right: in RTL the letters fall apart.
                canvas.dir = 'ltr';
                // In place at once, filling in as it is drawn.
                container.appendChild(canvas);
                setState('ready');

                await page.render({ canvasContext, viewport }).promise;

                const sheet = await new Promise<Blob | null>((resolve) =>
                    canvas.toBlob(resolve, 'image/png'),
                );

                if (cancelled || !sheet) {
                    return;
                }

                const sheetUrl = URL.createObjectURL(sheet);

                sheetUrls.push(sheetUrl);
                rendered.push(sheetUrl);
            }

            setSheets(rendered);
            notifyReady.current?.();
        };

        render().catch(() => {
            if (!cancelled) {
                setState('failed');
            }
        });

        return () => {
            cancelled = true;
            void task?.destroy();
            container?.replaceChildren();
            sheetUrls.forEach((sheetUrl) => URL.revokeObjectURL(sheetUrl));
        };
    }, [url]);

    return (
        <>
            {state === 'loading' && (
                <p
                    className="flex items-center justify-center gap-2 p-6 text-sm"
                    role="status"
                >
                    <Spinner /> {t('applicant.printed_loading')}
                </p>
            )}
            {state === 'failed' && (
                <p className="p-6 text-center text-sm text-muted-foreground">
                    {t('applicant.printed_failed')}
                </p>
            )}
            <div
                ref={holder}
                role="img"
                aria-label={title}
                className="grid gap-4"
                data-test="printed-form"
            />
            {createPortal(
                sheets.map((sheet, index) => (
                    <div key={index} className="print-sheet hidden bg-white">
                        <img
                            src={sheet}
                            alt=""
                            className="h-full w-full object-contain"
                        />
                    </div>
                )),
                document.body,
            )}
        </>
    );
}

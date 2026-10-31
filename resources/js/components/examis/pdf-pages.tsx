import type { PDFDocumentLoadingTask } from 'pdfjs-dist';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';

/** 216 dpi: sharp on paper as well as on high-density screens. */
const RENDER_SCALE = 3;

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

        const render = async () => {
            const [{ getDocument, GlobalWorkerOptions }, { default: PdfWorker }] =
                await Promise.all([
                    import('pdfjs-dist'),
                    // Built as a plain .js file: many servers do not send .mjs
                    // with a type browsers accept for a worker.
                    import('pdfjs-dist/build/pdf.worker.min.mjs?worker'),
                ]);

            if (cancelled || !container) {
                return;
            }

            GlobalWorkerOptions.workerPort ??= new PdfWorker();
            task = getDocument({
                url,
                isEvalSupported: false,
                disableRange: true,
                disableStream: true,
            });

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

                await page.render({ canvasContext, viewport }).promise;

                if (cancelled) {
                    return;
                }

                container.appendChild(canvas);
                rendered.push(canvas.toDataURL('image/png'));
            }

            setSheets(rendered);
            setState('ready');
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

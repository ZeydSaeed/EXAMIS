import type { PDFDocumentLoadingTask } from 'pdfjs-dist';
import { useEffect, useRef, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';

/** Enough detail to stay sharp on high-density screens. */
const MAX_PIXEL_RATIO = 2;

/**
 * Draws every page of a PDF at the full width of the page, one under the
 * other. Browsers' own PDF viewers cannot be restyled and do not exist on
 * most phones, so the file is rendered here instead. The library is only
 * loaded where this component is used.
 */
export function PdfPages({ url, title }: { url: string; title: string }) {
    const { t } = useTrans();
    const holder = useRef<HTMLDivElement>(null);
    const [state, setState] = useState<'loading' | 'ready' | 'failed'>(
        'loading',
    );

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
            const pixelRatio = Math.min(
                window.devicePixelRatio || 1,
                MAX_PIXEL_RATIO,
            );

            for (let number = 1; number <= document_.numPages; number++) {
                const page = await document_.getPage(number);
                const scale =
                    (container.clientWidth /
                        page.getViewport({ scale: 1 }).width) *
                    pixelRatio;
                const viewport = page.getViewport({ scale });
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
            }

            setState('ready');
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
        </>
    );
}

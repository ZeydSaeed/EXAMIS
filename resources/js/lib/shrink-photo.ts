/** The printed form uses 420×540 px; the server keeps at most 1200 px too. */
const MAX_SIDE = 1200;
const QUALITY = 0.85;

/**
 * Shrinks a phone photo before upload: a 3–8 MB camera picture becomes a
 * JPEG of about 150 KB, upright, so it passes the size limit and uploads in
 * seconds on a mobile connection. Anything that goes wrong returns the
 * original file, which the server still validates and re-encodes.
 */
export async function shrinkPhoto(file: File): Promise<File> {
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        const scale = Math.min(
            1,
            MAX_SIDE / Math.max(bitmap.width, bitmap.height),
        );

        // Already small: re-encoding would only cost quality.
        if (scale === 1 && file.size <= 300 * 1024) {
            bitmap.close();

            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);

        const context = canvas.getContext('2d');

        if (!context) {
            bitmap.close();

            return file;
        }

        // White under transparent PNG areas, as on the printed form.
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', QUALITY),
        );

        if (!blob || blob.size >= file.size) {
            return file;
        }

        const name = file.name.replace(/\.[^.]*$/, '') || 'photo';

        return new File([blob], `${name}.jpg`, { type: 'image/jpeg' });
    } catch {
        return file;
    }
}

/**
 * When a JPEG was taken, from its EXIF data (DateTimeOriginal, else
 * DateTime), in the device's local time. Null when the file is not a JPEG
 * or carries no date, as with scans, edited or forwarded photos.
 */
export async function photoTakenAt(file: File): Promise<Date | null> {
    const view = new DataView(await file.slice(0, 128 * 1024).arrayBuffer());

    if (view.byteLength < 4 || view.getUint16(0) !== 0xffd8) {
        return null;
    }

    let offset = 2;

    while (offset + 4 <= view.byteLength) {
        const marker = view.getUint16(offset);
        const length = view.getUint16(offset + 2);

        if (
            marker === 0xffe1 &&
            readAscii(view, offset + 4, 6) === 'Exif\0\0'
        ) {
            return readExifDate(view, offset + 10);
        }

        if ((marker & 0xff00) !== 0xff00 || marker === 0xffda) {
            return null;
        }

        offset += 2 + length;
    }

    return null;
}

function readExifDate(view: DataView, tiff: number): Date | null {
    if (tiff + 8 > view.byteLength) {
        return null;
    }

    const little = view.getUint16(tiff) === 0x4949;
    const u16 = (at: number) => view.getUint16(at, little);
    const u32 = (at: number) => view.getUint32(at, little);

    const entries = (ifd: number) => {
        const found = new Map<number, number>();

        if (ifd + 2 > view.byteLength) {
            return found;
        }

        const count = u16(ifd);

        for (let i = 0; i < count; i++) {
            const entry = ifd + 2 + i * 12;

            if (entry + 12 > view.byteLength) {
                break;
            }

            found.set(u16(entry), entry);
        }

        return found;
    };

    const dateAt = (entry: number | undefined) => {
        if (entry === undefined) {
            return null;
        }

        const text = readAscii(view, tiff + u32(entry + 8), 19);
        const match = /^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})$/.exec(
            text,
        );

        if (!match) {
            return null;
        }

        const [, year, month, day, hour, minute, second] = match.map(Number);

        return new Date(year, month - 1, day, hour, minute, second);
    };

    const ifd0 = entries(tiff + u32(tiff + 4));
    const exifPointer = ifd0.get(0x8769);
    const exif =
        exifPointer === undefined
            ? new Map<number, number>()
            : entries(tiff + u32(exifPointer + 8));

    return dateAt(exif.get(0x9003)) ?? dateAt(ifd0.get(0x0132));
}

function readAscii(view: DataView, start: number, length: number): string {
    if (start < 0 || start + length > view.byteLength) {
        return '';
    }

    let text = '';

    for (let i = 0; i < length; i++) {
        text += String.fromCharCode(view.getUint8(start + i));
    }

    return text;
}

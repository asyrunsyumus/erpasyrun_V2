/**
 * Nomor ke tautan. Nomor yang diawali 0 dianggap nomor Indonesia (62) kecuali diberi kode negara,
 * mis. +20 untuk Mesir. Hasilnya hanya digit, sesuai format wa.me.
 */
export function waLink(raw: string | null | undefined, defaultCountry = '62'): string | null {
    if (!raw) return null;
    let d = raw.replace(/\D/g, '');
    if (!d) return null;
    if (raw.trim().startsWith('+')) {
        // sudah pakai kode negara
    } else if (d.startsWith('00')) {
        d = d.slice(2);
    } else if (d.startsWith('0')) {
        d = defaultCountry + d.slice(1);
    }
    return `https://wa.me/${d}`;
}

/** Tautan telepon: tel:+kode…, hanya tanda + dan digit. */
export function telLink(raw: string | null | undefined): string | null {
    if (!raw) return null;
    const t = raw.replace(/[^\d+]/g, '');
    return t.replace(/\D/g, '') ? `tel:${t}` : null;
}

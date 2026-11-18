/** Membaca alamat yang ditempel (mis. dari WhatsApp) untuk mengisi kelurahan, kecamatan, dan kode pos. Hasilnya hanya saran; pengisi tetap mengecek. */
export interface AddressParts {
    kelurahan?: string;
    kecamatan?: string;
    kode_pos?: string;
}

export function parseAddress(text: string): AddressParts {
    const out: AddressParts = {};
    const pos = text.match(/\b(\d{5})\b/);
    if (pos) out.kode_pos = pos[1];

    const kel = text.match(/\bkel(?:urahan)?\.?\s*:?\s*([^,\n;]+)/i);
    if (kel) out.kelurahan = kel[1].replace(/\b(kec|kecamatan|kode pos|kodepos)\b.*$/i, '').trim();

    const kec = text.match(/\bkec(?:amatan)?\.?\s*:?\s*([^,\n;]+)/i);
    if (kec) out.kecamatan = kec[1].replace(/\b(kode pos|kodepos|\d{5})\b.*$/i, '').trim();

    return out;
}

/** Link pencarian Google Maps dari alamat (tanpa API key). Pengisi harus mengkonfirmasi sebelum disimpan. */
export function mapsSearchUrl(parts: string[]): string {
    const query = parts.map((p) => p.trim()).filter(Boolean).join(', ');
    return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query);
}

/** Rp 2.195.000 */
export function rupiah(n: number): string {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

/** Rp 48,2 jt · Rp 950 rb — untuk angka ringkas di dashboard. */
export function rupiahShort(n: number): string {
    const sign = n < 0 ? '− ' : '';
    const v = Math.abs(n);
    if (v >= 1_000_000_000) return `${sign}Rp ${(v / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} M`;
    if (v >= 1_000_000) return `${sign}Rp ${(v / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
    if (v >= 1_000) return `${sign}Rp ${Math.round(v / 1_000).toLocaleString('id-ID')} rb`;
    return `${sign}Rp ${v.toLocaleString('id-ID')}`;
}

/** 27,2 kg */
export function kg(grams: number): string {
    return (grams / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' kg';
}

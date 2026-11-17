import { Btn, ErrorBanner, PageHeader, Segmented } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    line: number;
    data: Record<string, string | number | null>;
    errors: string[];
    action: 'baru' | 'perbarui';
}
interface Props {
    import: { id: number; type: string; label: string; status: string; rows_ok: number; rows_failed: number };
    missing: string[];
    rows: Row[];
    truncated: boolean;
}

const COLS: Record<string, [string, string][]> = {
    products: [
        ['sku', 'SKU'],
        ['title_latin', 'Judul'],
        ['title_ar', 'Arab'],
        ['weight_grams', 'Berat (g)'],
        ['maktabah', 'Maktabah'],
        ['price_regular', 'Reguler'],
        ['price_reseller', 'Reseller'],
        ['initial_stock', 'Stok awal'],
    ],
    customers: [
        ['code', 'Kode'],
        ['name', 'Nama'],
        ['type', 'Tipe'],
        ['phone', 'Telepon'],
        ['city', 'Kota'],
    ],
    maktabahs: [
        ['name', 'Nama'],
        ['area', 'Area'],
        ['phone', 'Telepon'],
    ],
};

export default function ImportPreview({ import: imp, missing, rows, truncated }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [show, setShow] = useState<'semua' | 'masalah'>(imp.rows_failed > 0 ? 'masalah' : 'semua');
    const [busy, setBusy] = useState(false);
    const cols = COLS[imp.type].filter(([k]) => rows.some((r) => k in r.data));
    const list = show === 'masalah' ? rows.filter((r) => r.errors.length) : rows;
    const done = imp.status !== 'preview';

    return (
        <ErpLayout>
            <Head title="Pratinjau import" />
            <PageHeader title={`Pratinjau import ${imp.label}`} subtitle="Periksa dulu. Baris bermasalah akan dilewati, baris lain tetap disimpan.">
                <Btn onClick={() => router.visit(`/imports?type=${imp.type}`)}>← Ganti file</Btn>
                <Btn
                    variant="primary"
                    disabled={done || busy || missing.length > 0 || imp.rows_ok === 0}
                    onClick={() => {
                        setBusy(true);
                        router.post(`/imports/${imp.id}/confirm`, {}, { onFinish: () => setBusy(false) });
                    }}
                >
                    {busy ? 'Menyimpan…' : `Simpan ${imp.rows_ok} baris`}
                </Btn>
            </PageHeader>

            <ErrorBanner message={errors.import} />
            {missing.length > 0 && <ErrorBanner message={`Kolom wajib tidak ditemukan di file: ${missing.join(', ')}. Periksa judul kolom di baris pertama.`} />}

            <div className="mb-4 grid grid-cols-2 gap-3 sm:max-w-md">
                <div className="card !py-4">
                    <div className="text-ink-2 text-sm">Siap disimpan</div>
                    <div className="font-display num text-ok text-2xl font-semibold">{imp.rows_ok}</div>
                </div>
                <div className={'card !py-4 ' + (imp.rows_failed ? 'border-bad' : '')}>
                    <div className="text-ink-2 text-sm">Bermasalah</div>
                    <div className={'font-display num text-2xl font-semibold ' + (imp.rows_failed ? 'text-bad' : 'text-ink-3')}>{imp.rows_failed}</div>
                </div>
            </div>

            <div className="mb-3">
                <Segmented
                    value={show}
                    options={[
                        { value: 'masalah', label: `Bermasalah (${imp.rows_failed})` },
                        { value: 'semua', label: 'Semua baris' },
                    ]}
                    onChange={setShow}
                />
            </div>

            <div className="card overflow-x-auto !p-0">
                <table className="num w-full min-w-[720px] text-sm">
                    <thead>
                        <tr className="text-ink-3 border-line border-b text-left text-[13px] [&>th]:px-3 [&>th]:py-2.5 [&>th]:font-medium">
                            <th>Baris</th>
                            <th>Aksi</th>
                            {cols.map(([k, l]) => (
                                <th key={k}>{l}</th>
                            ))}
                            <th>Masalah</th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((r) => (
                            <tr key={r.line} className={'border-line border-b last:border-0 [&>td]:px-3 [&>td]:py-2 ' + (r.errors.length ? 'bg-bad-soft/50' : '')}>
                                <td className="text-ink-3">{r.line}</td>
                                <td>
                                    {r.errors.length ? (
                                        <span className="text-bad font-medium">dilewati</span>
                                    ) : (
                                        <span className={r.action === 'baru' ? 'text-ok font-medium' : 'text-brand font-medium'}>{r.action}</span>
                                    )}
                                </td>
                                {cols.map(([k]) => (
                                    <td key={k} className={k === 'title_ar' ? 'ar text-left' : ''}>
                                        {r.data[k] === null || r.data[k] === '' ? <span className="text-ink-3">—</span> : String(r.data[k])}
                                    </td>
                                ))}
                                <td className="text-bad">{r.errors.join('; ')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {list.length === 0 && <p className="text-ink-3 py-10 text-center text-sm">Tidak ada baris bermasalah.</p>}
            </div>
            {truncated && <p className="text-ink-3 mt-3 text-sm">Pratinjau hanya menampilkan 500 baris pertama. Semua baris tetap diproses saat disimpan.</p>}
        </ErpLayout>
    );
}

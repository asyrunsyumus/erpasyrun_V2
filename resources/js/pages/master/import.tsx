import { Btn, PageHeader } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';

interface TypeOpt {
    key: string;
    label: string;
    columns: string[];
}

export default function ImportPage({ types, selected, isOwner }: { types: TypeOpt[]; selected: string; isOwner: boolean }) {
    const form = useForm<{ type: string; file: File | null }>({ type: selected, file: null });
    const [drag, setDrag] = useState(false);
    const current = types.find((t) => t.key === form.data.type)!;
    const cols = current.columns.filter((c) => isOwner || !(c.startsWith('harga') || c === 'stok_awal'));

    return (
        <ErpLayout>
            <Head title="Import data" />
            <PageHeader title="Import data dari Excel" subtitle="Unggah file, periksa pratinjaunya, lalu simpan. Tidak ada yang tersimpan sebelum Anda menekan Simpan." />

            <div className="grid gap-4 lg:grid-cols-[1fr_340px]">
                <form
                    className="card grid content-start gap-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/imports', { forceFormData: true });
                    }}
                >
                    <div>
                        <p className="mb-2 text-sm font-medium">1. Apa yang mau diimport?</p>
                        <div className="flex flex-wrap gap-2">
                            {types.map((t) => (
                                <button
                                    type="button"
                                    key={t.key}
                                    onClick={() => form.setData('type', t.key)}
                                    className={'h-11 rounded-[10px] border px-4 font-medium ' + (form.data.type === t.key ? 'border-brand bg-brand-soft text-brand' : 'border-line text-ink-2')}
                                >
                                    {t.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div>
                        <p className="mb-2 text-sm font-medium">2. Pilih file (.xlsx atau .csv)</p>
                        <label
                            onDragOver={(e) => {
                                e.preventDefault();
                                setDrag(true);
                            }}
                            onDragLeave={() => setDrag(false)}
                            onDrop={(e) => {
                                e.preventDefault();
                                setDrag(false);
                                form.setData('file', e.dataTransfer.files[0] ?? null);
                            }}
                            className={'flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center ' + (drag ? 'border-brand bg-brand-soft' : 'border-line hover:border-brand')}
                        >
                            <FileSpreadsheet className="text-brand size-8" />
                            {form.data.file ? (
                                <span className="font-medium">{form.data.file.name}</span>
                            ) : (
                                <>
                                    <span className="font-medium">Klik untuk memilih file</span>
                                    <span className="text-ink-3 text-sm">atau seret file ke sini · maksimal 10 MB, 5.000 baris</span>
                                </>
                            )}
                            <input type="file" accept=".xlsx,.csv" className="hidden" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
                        </label>
                        {form.errors.file && <p className="text-bad mt-2 text-[13px]">{form.errors.file}</p>}
                    </div>

                    <div className="flex justify-end">
                        <Btn variant="primary" disabled={!form.data.file || form.processing}>
                            {form.processing ? 'Membaca file…' : 'Lihat pratinjau →'}
                        </Btn>
                    </div>
                </form>

                <aside className="card grid content-start gap-3 text-sm">
                    <p className="font-medium">Format kolom {current.label}</p>
                    <p className="text-ink-2">Baris pertama harus berisi judul kolom berikut. Urutan bebas. Kolom bertanda * wajib.</p>
                    <div className="flex flex-wrap gap-1.5">
                        {cols.map((c) => (
                            <code key={c} className={'rounded-md px-2 py-0.5 text-[12.5px] ' + (c.endsWith('*') ? 'bg-brand-soft text-brand font-semibold' : 'bg-soft text-ink-2')}>
                                {c}
                            </code>
                        ))}
                    </div>
                    {form.data.type === 'products' && (
                        <ul className="text-ink-2 list-disc space-y-1 pl-4">
                            <li>SKU sama dengan yang sudah ada → kitab itu diperbarui.</li>
                            <li>
                                <code>maktabah_utama</code> harus sama persis dengan nama di menu Maktabah.
                            </li>
                            {isOwner && (
                                <li>
                                    <code>stok_awal</code> hanya dipakai sekali, untuk kitab yang belum punya catatan stok.
                                </li>
                            )}
                        </ul>
                    )}
                    {form.data.type === 'customers' && <p className="text-ink-2">Kolom tipe diisi "reguler" atau "reseller". Kosong = reguler.</p>}
                    <a href={`/imports/template/${form.data.type}`} className="text-brand mt-1 inline-flex items-center gap-1.5 font-medium">
                        <Download className="size-4" /> Unduh template
                    </a>
                </aside>
            </div>
        </ErpLayout>
    );
}

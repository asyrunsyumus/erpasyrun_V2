import { Btn, Field, inputCls, PageHeader } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, useForm, usePage } from '@inertiajs/react';

/**
 * Unduh cadangan: form HTML biasa (bukan Inertia) agar browser menerima file.
 * Password salah → server mengembalikan ke halaman ini dengan pesan error.
 */
function BackupCard() {
    const { errors, csrf } = usePage().props as unknown as { errors: Record<string, string>; csrf: string };
    const token = csrf;
    return (
        <form method="post" action="/backup" className="card mt-4 grid max-w-2xl gap-4">
            <input type="hidden" name="_token" value={token} />
            <div>
                <h2 className="text-lg font-semibold">Cadangan data</h2>
                <p className="text-ink-2 mt-1 text-sm">
                    Unduh salinan seluruh data (produk, customer, pesanan, belanja, stok, keuangan) sebagai file .sql. Simpan di luar server, misalnya Google Drive pribadi. Sebaiknya seminggu sekali, dan
                    sebelum perubahan besar.
                </p>
                <p className="text-warn mt-2 text-sm font-medium">File ini rahasia — berisi data pembeli dan akun. Jangan dikirim lewat grup WhatsApp.</p>
            </div>
            <div className="flex flex-wrap items-end gap-3">
                <Field label="Ketik ulang password Anda" error={errors.password} className="w-64">
                    <input type="password" name="password" className={inputCls} autoComplete="current-password" required />
                </Field>
                <Btn variant="primary">Unduh cadangan</Btn>
            </div>
        </form>
    );
}

export default function ShopSettings({ values }: { values: { shop_phone: string; invoice_bank: string; invoice_note: string } }) {
    const form = useForm(values);
    return (
        <ErpLayout>
            <Head title="Toko & Invoice" />
            <PageHeader title="Toko & Invoice" subtitle="Data ini tampil di setiap invoice yang dicetak." />
            <form
                className="card grid max-w-2xl gap-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/shop-settings', { preserveScroll: true });
                }}
            >
                <Field label="Nomor WhatsApp toko" error={form.errors.shop_phone}>
                    <input className={inputCls} inputMode="tel" value={form.data.shop_phone} onChange={(e) => form.setData('shop_phone', e.target.value)} placeholder="0812 …" />
                </Field>
                <Field label="Rekening pembayaran" error={form.errors.invoice_bank} hint="Boleh lebih dari satu baris.">
                    <textarea className={inputCls + ' h-24 py-2'} value={form.data.invoice_bank} onChange={(e) => form.setData('invoice_bank', e.target.value)} placeholder={'BSI 1234567890 a.n. Maktabah Asyrun Syumus'} />
                </Field>
                <Field label="Catatan di bawah invoice" error={form.errors.invoice_note}>
                    <textarea className={inputCls + ' h-24 py-2'} value={form.data.invoice_note} onChange={(e) => form.setData('invoice_note', e.target.value)} placeholder="mis. Mohon kirim bukti transfer ke WhatsApp toko." />
                </Field>
                <div className="flex justify-end">
                    <Btn variant="primary" disabled={form.processing || !form.isDirty}>
                        Simpan
                    </Btn>
                </div>
            </form>

            <BackupCard />
        </ErpLayout>
    );
}

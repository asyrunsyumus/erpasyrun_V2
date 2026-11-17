import { Btn, Field, inputCls, PageHeader } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, useForm } from '@inertiajs/react';

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
        </ErpLayout>
    );
}

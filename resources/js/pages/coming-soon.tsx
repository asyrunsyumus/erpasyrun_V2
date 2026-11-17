import ErpLayout from '@/layouts/erp-layout';
import { Head, Link } from '@inertiajs/react';
import { Hammer } from 'lucide-react';

export default function ComingSoon({ title }: { title: string }) {
    return (
        <ErpLayout>
            <Head title={title} />
            <h1 className="mb-6 text-[26px] font-semibold">{title}</h1>
            <div className="card flex flex-col items-center gap-3 py-16 text-center">
                <span className="bg-brand-soft text-brand grid size-12 place-items-center rounded-full">
                    <Hammer className="size-6" />
                </span>
                <p className="font-medium">Halaman ini sedang disiapkan.</p>
                <p className="text-ink-3 max-w-md text-sm">Menu ini sudah muncul karena memang bagian dari tugas Anda. Fiturnya akan aktif di tahap pembangunan berikutnya.</p>
                <Link href="/dashboard" className="text-brand mt-2 text-sm font-medium">
                    ← Kembali ke Beranda
                </Link>
            </div>
        </ErpLayout>
    );
}

import { Btn, Field, inputCls } from '@/components/erp';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Copy, ShieldAlert, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

interface Props {
    enabled: boolean;
    required: boolean;
    setup: { qr: string; secret: string } | null;
    recoveryLeft: number;
    recoveryCodes: string[] | null;
}

export default function TwoFactorSettings({ enabled, required, setup, recoveryLeft, recoveryCodes }: Props) {
    const confirm = useForm({ code: '' });
    const regen = useForm({ password: '' });
    const off = useForm({ password: '' });
    const [copied, setCopied] = useState(false);

    return (
        <AppLayout>
            <Head title="Verifikasi dua langkah" />
            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-start gap-3">
                        {enabled ? <ShieldCheck className="text-ok mt-0.5 size-6 shrink-0" /> : <ShieldAlert className="text-warn mt-0.5 size-6 shrink-0" />}
                        <div>
                            <h2 className="text-lg font-semibold">Verifikasi dua langkah {enabled ? 'aktif' : 'belum aktif'}</h2>
                            <p className="text-ink-2 mt-1 text-sm">
                                Selain password, setiap masuk diminta kode 6 digit dari aplikasi di HP (Google Authenticator atau Microsoft Authenticator). Orang yang tahu password Anda tetap tidak bisa masuk tanpa HP Anda.
                            </p>
                            {required && !enabled && <p className="bg-warn-soft text-warn mt-3 rounded-lg px-3 py-2 text-sm font-medium">Wajib untuk Owner. Menu lain terbuka setelah langkah ini selesai.</p>}
                        </div>
                    </div>

                    {recoveryCodes && (
                        <div className="border-gold bg-gold-soft rounded-xl border p-4">
                            <p className="font-medium">Kode pemulihan — simpan sekarang, hanya tampil sekali</p>
                            <p className="text-ink-2 mt-1 text-sm">Pakai salah satu bila HP hilang. Setiap kode hanya bisa dipakai sekali. Cetak atau simpan di tempat aman (bukan di HP yang sama).</p>
                            <div className="num bg-surface mt-3 grid grid-cols-2 gap-1.5 rounded-lg p-3 font-mono text-[15px] select-all">
                                {recoveryCodes.map((c) => (
                                    <span key={c}>{c}</span>
                                ))}
                            </div>
                            <Btn size="sm" className="mt-3" onClick={() => navigator.clipboard?.writeText(recoveryCodes.join('\n')).then(() => setCopied(true))}>
                                <Copy className="size-4" /> {copied ? 'Tersalin' : 'Salin semua'}
                            </Btn>
                        </div>
                    )}

                    {!enabled && !setup && (
                        <Btn variant="primary" onClick={() => router.post('/settings/two-factor/start', {}, { preserveScroll: true })}>
                            Aktifkan sekarang
                        </Btn>
                    )}

                    {!enabled && setup && (
                        <div className="grid gap-5">
                            <ol className="text-ink-2 list-decimal space-y-1 pl-5 text-sm">
                                <li>Pasang aplikasi Google Authenticator atau Microsoft Authenticator di HP.</li>
                                <li>Di aplikasi, pilih tambah akun → pindai kode QR di bawah.</li>
                                <li>Ketik 6 angka yang muncul di aplikasi, lalu tekan Konfirmasi.</li>
                            </ol>
                            <div className="flex flex-wrap items-center gap-5">
                                <div className="rounded-xl border border-[#e3e6e3] bg-white p-3" dangerouslySetInnerHTML={{ __html: setup.qr }} />
                                <div className="text-sm">
                                    <p className="text-ink-3">Tidak bisa memindai? Ketik kunci ini:</p>
                                    <p className="num mt-1 font-mono text-[15px] font-medium tracking-wide select-all">{setup.secret}</p>
                                </div>
                            </div>
                            <form
                                className="flex flex-wrap items-end gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    confirm.post('/settings/two-factor/confirm', { preserveScroll: true, onSuccess: () => confirm.reset() });
                                }}
                            >
                                <Field label="Kode 6 digit" error={confirm.errors.code} className="w-44">
                                    <input
                                        className={inputCls + ' num text-center text-xl tracking-[0.3em]'}
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        value={confirm.data.code}
                                        onChange={(e) => confirm.setData('code', e.target.value.replace(/\D/g, ''))}
                                        autoFocus
                                    />
                                </Field>
                                <Btn variant="primary" disabled={confirm.data.code.length !== 6 || confirm.processing}>
                                    Konfirmasi
                                </Btn>
                            </form>
                        </div>
                    )}

                    {enabled && (
                        <div className="grid gap-6">
                            <form
                                className="grid gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    regen.post('/settings/two-factor/recovery-codes', { preserveScroll: true, onSuccess: () => regen.reset() });
                                }}
                            >
                                <p className="text-sm">
                                    Sisa kode pemulihan: <b className="num">{recoveryLeft}</b> dari 8.{recoveryLeft <= 2 && <span className="text-warn"> Sebaiknya buat yang baru.</span>}
                                </p>
                                <div className="flex flex-wrap items-end gap-3">
                                    <Field label="Password Anda" error={regen.errors.password} className="w-64">
                                        <input type="password" className={inputCls} value={regen.data.password} onChange={(e) => regen.setData('password', e.target.value)} autoComplete="current-password" />
                                    </Field>
                                    <Btn disabled={!regen.data.password || regen.processing}>Buat kode pemulihan baru</Btn>
                                </div>
                            </form>

                            {!required && (
                                <form
                                    className="border-line grid gap-3 border-t pt-5"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        off.delete('/settings/two-factor', { preserveScroll: true });
                                    }}
                                >
                                    <p className="text-ink-2 text-sm">Mematikan verifikasi dua langkah membuat akun hanya dilindungi password.</p>
                                    <div className="flex flex-wrap items-end gap-3">
                                        <Field label="Password Anda" error={off.errors.password} className="w-64">
                                            <input type="password" className={inputCls} value={off.data.password} onChange={(e) => off.setData('password', e.target.value)} autoComplete="current-password" />
                                        </Field>
                                        <Btn variant="danger" disabled={!off.data.password || off.processing}>
                                            Matikan
                                        </Btn>
                                    </div>
                                </form>
                            )}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

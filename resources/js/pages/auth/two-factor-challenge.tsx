import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function TwoFactorChallenge() {
    const [recovery, setRecovery] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });

    return (
        <AuthLayout title="Verifikasi dua langkah" description={recovery ? 'Ketik salah satu kode pemulihan Anda.' : 'Buka aplikasi authenticator di HP, lalu ketik 6 angka yang muncul.'}>
            <Head title="Verifikasi" />
            <form
                className="grid gap-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((d) => (recovery ? { recovery_code: d.recovery_code } : { code: d.code }));
                    form.post(route('two-factor.verify'), { onFinish: () => form.reset() });
                }}
            >
                {recovery ? (
                    <div className="grid gap-2">
                        <Label htmlFor="recovery_code">Kode pemulihan</Label>
                        <Input id="recovery_code" className="h-11 font-mono" autoFocus autoComplete="off" value={form.data.recovery_code} onChange={(e) => form.setData('recovery_code', e.target.value)} placeholder="xxxxx-xxxxx" />
                        <InputError message={form.errors.recovery_code} />
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="code">Kode 6 digit</Label>
                        <Input
                            id="code"
                            className="h-12 text-center text-2xl tracking-[0.4em]"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            maxLength={6}
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.replace(/\D/g, ''))}
                        />
                        <InputError message={form.errors.code} />
                    </div>
                )}
                <Button type="submit" className="h-11 w-full text-[15px]" disabled={form.processing || (!recovery && form.data.code.length !== 6)}>
                    Masuk
                </Button>
                <div className="flex justify-between text-sm">
                    <button type="button" className="text-brand font-medium" onClick={() => setRecovery(!recovery)}>
                        {recovery ? 'Pakai kode dari aplikasi' : 'HP hilang? Pakai kode pemulihan'}
                    </button>
                    <Link href={route('login')} className="text-ink-3">
                        Kembali
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}

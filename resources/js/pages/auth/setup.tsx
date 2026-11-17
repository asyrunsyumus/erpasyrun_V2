import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function Setup({ token }: { token: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('setup.store', token), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title="Buat akun Owner" description="Halaman ini hanya bisa dipakai sekali. Setelah akun dibuat, halaman ini tertutup.">
            <Head title="Setup Owner" />

            <form className="grid gap-5" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="name">Nama lengkap</Label>
                    <Input id="name" className="h-11" required autoFocus value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="name" />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" className="h-11" required value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="email" />
                    <InputError message={errors.email} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input id="password" type="password" className="h-11" required value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="new-password" />
                    <p className="text-ink-3 text-xs">Minimal 10 karakter, berisi huruf dan angka.</p>
                    <InputError message={errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Ulangi password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        className="h-11"
                        required
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                    />
                </div>
                <Button type="submit" className="mt-2 h-11 w-full text-[15px]" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Buat akun Owner
                </Button>
            </form>
        </AuthLayout>
    );
}

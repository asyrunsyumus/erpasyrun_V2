import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

export default function Profile() {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Profil" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Profil" description="Email dan peran hanya bisa diubah oleh Owner." />

                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama</Label>
                            <Input id="name" className="h-11" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoComplete="name" />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Email</Label>
                            <p className="text-ink-2">{auth.user.email}</p>
                        </div>

                        <div className="grid gap-2">
                            <Label>Peran</Label>
                            <p className="text-ink-2">{auth.user.role_label}</p>
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Simpan</Button>
                            {recentlySuccessful && <p className="text-ok text-sm">Tersimpan</p>}
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

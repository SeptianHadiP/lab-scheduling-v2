import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Account = { id: number; name: string; email: string; role: string; lab_id: number | null };

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs';

export default function AccountForm({
    account,
    roles,
    labs,
}: {
    account: Account | null;
    roles: { value: string; label: string }[];
    labs: { id: number; name: string }[];
}) {
    const isEdit = account !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: account?.name ?? '',
        email: account?.email ?? '',
        password: '',
        role: account?.role ?? 'dosen',
        lab_id: account?.lab_id ? String(account.lab_id) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (account) {
            put(`/accounts/${account.id}`);
        } else {
            post('/accounts');
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Akun' : 'Tambah Akun'} />
            <form onSubmit={submit} className="max-w-lg space-y-5 p-4">
                <h1 className="text-xl font-semibold">{isEdit ? 'Ubah Akun' : 'Tambah Akun'}</h1>

                <div className="grid gap-2">
                    <Label htmlFor="name">Nama</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">{isEdit ? 'Password baru (kosongkan jika tidak diubah)' : 'Password awal'}</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="role">Role</Label>
                    <select id="role" className={selectClass} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                        {roles.map((r) => (
                            <option key={r.value} value={r.value}>{r.label}</option>
                        ))}
                    </select>
                    <InputError message={errors.role} />
                </div>

                {(data.role === 'asisten_lab' || data.role === 'ketua_lab') && (
                    <div className="grid gap-2">
                        <Label htmlFor="lab_id">Laboratorium</Label>
                        <select id="lab_id" className={selectClass} value={data.lab_id} onChange={(e) => setData('lab_id', e.target.value)}>
                            <option value="">Pilih laboratorium</option>
                            {labs.map((l) => (
                                <option key={l.id} value={l.id}>{l.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.lab_id} />
                    </div>
                )}

                <div className="flex gap-2">
                    <Button type="submit" disabled={processing}>Simpan</Button>
                    <Button asChild variant="outline">
                        <Link href="/accounts">Batal</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

AccountForm.layout = {
    breadcrumbs: [
        { title: 'Kelola Akun', href: '/accounts' },
        { title: 'Form', href: '#' },
    ],
};
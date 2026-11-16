import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Course = { id: number; code: string; name: string; is_active: boolean };

export default function Index({ courses }: { courses: Course[] }) {
    const [editing, setEditing] = useState<Course | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        code: '',
        name: '',
    });

    const startEdit = (c: Course) => {
        setEditing(c);
        setData({ code: c.code, name: c.name });
        clearErrors();
    };

    const cancel = () => {
        setEditing(null);
        reset();
        clearErrors();
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: cancel };
        if (editing) {
            put(`/courses/${editing.id}`, options);
        } else {
            post('/courses', options);
        }
    };

    const toggle = (id: number) =>
        router.patch(`/courses/${id}/status`, {}, { preserveScroll: true });

    return (
        <>
            <Head title="Mata Kuliah" />
            <div className="space-y-4 p-4">
                <h1 className="text-xl font-semibold">Mata Kuliah</h1>

                <form onSubmit={submit} className="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-[160px_1fr_auto] md:items-start dark:border-sidebar-border">
                    <div className="grid gap-1">
                        <Label htmlFor="code">Kode</Label>
                        <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                        <InputError message={errors.code} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="name">Nama mata kuliah</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="flex gap-2 md:mt-6">
                        <Button type="submit" disabled={processing}>{editing ? 'Simpan' : 'Tambah'}</Button>
                        {editing && <Button type="button" variant="outline" onClick={cancel}>Batal</Button>}
                    </div>
                </form>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="p-3">Kode</th>
                                <th className="p-3">Nama</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {courses.length === 0 && (
                                <tr><td className="p-3" colSpan={4}>Belum ada mata kuliah.</td></tr>
                            )}
                            {courses.map((c) => (
                                <tr key={c.id} className="border-b last:border-0">
                                    <td className="p-3">{c.code}</td>
                                    <td className="p-3">{c.name}</td>
                                    <td className="p-3">
                                        <Badge variant={c.is_active ? 'secondary' : 'destructive'}>
                                            {c.is_active ? 'Aktif' : 'Diarsipkan'}
                                        </Badge>
                                    </td>
                                    <td className="flex gap-2 p-3">
                                        <Button variant="outline" size="sm" onClick={() => startEdit(c)}>Ubah</Button>
                                        <Button variant="outline" size="sm" onClick={() => toggle(c.id)}>
                                            {c.is_active ? 'Arsipkan' : 'Aktifkan'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: 'Mata Kuliah', href: '/courses' }],
};
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ClassRow = {
    id: number;
    code: string;
    academic_year: string;
    course_id: number;
    course: string;
    dosen_id: number;
    dosen: string;
    is_active: boolean;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs';

export default function Index({
    classes,
    courses,
    dosens,
}: {
    classes: ClassRow[];
    courses: { id: number; code: string; name: string }[];
    dosens: { id: number; name: string }[];
}) {
    const [editing, setEditing] = useState<ClassRow | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        course_id: '',
        code: '',
        dosen_id: '',
        academic_year: '',
    });

    const startEdit = (c: ClassRow) => {
        setEditing(c);
        setData({
            course_id: String(c.course_id),
            code: c.code,
            dosen_id: String(c.dosen_id),
            academic_year: c.academic_year,
        });
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
            put(`/classes/${editing.id}`, options);
        } else {
            post('/classes', options);
        }
    };

    const toggle = (id: number) =>
        router.patch(`/classes/${id}/status`, {}, { preserveScroll: true });

    return (
        <>
            <Head title="Kelas" />
            <div className="space-y-4 p-4">
                <h1 className="text-xl font-semibold">Kelas</h1>

                <form onSubmit={submit} className="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-2 dark:border-sidebar-border">
                    <div className="grid gap-1">
                        <Label htmlFor="course_id">Mata kuliah</Label>
                        <select id="course_id" className={selectClass} value={data.course_id} onChange={(e) => setData('course_id', e.target.value)}>
                            <option value="">Pilih mata kuliah</option>
                            {courses.map((c) => (
                                <option key={c.id} value={c.id}>{c.code} - {c.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.course_id} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="dosen_id">Dosen pengampu</Label>
                        <select id="dosen_id" className={selectClass} value={data.dosen_id} onChange={(e) => setData('dosen_id', e.target.value)}>
                            <option value="">Pilih dosen</option>
                            {dosens.map((d) => (
                                <option key={d.id} value={d.id}>{d.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.dosen_id} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="code">Kode kelas</Label>
                        <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                        <InputError message={errors.code} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="academic_year">Tahun akademik</Label>
                        <Input id="academic_year" placeholder="2026/2027" value={data.academic_year} onChange={(e) => setData('academic_year', e.target.value)} />
                        <InputError message={errors.academic_year} />
                    </div>
                    <div className="flex gap-2 md:col-span-2">
                        <Button type="submit" disabled={processing}>{editing ? 'Simpan' : 'Tambah'}</Button>
                        {editing && <Button type="button" variant="outline" onClick={cancel}>Batal</Button>}
                    </div>
                </form>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="p-3">Tahun</th>
                                <th className="p-3">Kelas</th>
                                <th className="p-3">Mata kuliah</th>
                                <th className="p-3">Dosen</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {classes.length === 0 && (
                                <tr><td className="p-3" colSpan={6}>Belum ada kelas.</td></tr>
                            )}
                            {classes.map((c) => (
                                <tr key={c.id} className="border-b last:border-0">
                                    <td className="p-3">{c.academic_year}</td>
                                    <td className="p-3">{c.code}</td>
                                    <td className="p-3">{c.course}</td>
                                    <td className="p-3">{c.dosen}</td>
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
    breadcrumbs: [{ title: 'Kelas', href: '/classes' }],
};
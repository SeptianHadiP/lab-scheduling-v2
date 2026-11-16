import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Facility = {
    id: number;
    name: string;
    quantity: number;
    unit: string | null;
    note: string | null;
};

const selectClass =
    'h-9 w-full max-w-sm rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs';

export default function Index({
    labs,
    selectedLabId,
    canManage,
    facilities,
}: {
    labs: { id: number; name: string }[];
    selectedLabId: number | null;
    canManage: boolean;
    facilities: Facility[];
}) {
    const [editing, setEditing] = useState<Facility | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        quantity: '1',
        unit: '',
        note: '',
    });

    const cancel = () => {
        setEditing(null);
        reset();
        clearErrors();
    };

    const changeLab = (id: string) => {
        cancel();
        router.get('/facilities', { lab: id });
    };

    const startEdit = (f: Facility) => {
        setEditing(f);
        setData({
            name: f.name,
            quantity: String(f.quantity),
            unit: f.unit ?? '',
            note: f.note ?? '',
        });
        clearErrors();
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: cancel };
        if (editing) {
            put(`/facilities/${editing.id}`, options);
        } else if (selectedLabId) {
            post(`/labs/${selectedLabId}/facilities`, options);
        }
    };

    const remove = (f: Facility) => {
        if (window.confirm(`Hapus "${f.name}" dari daftar fasilitas?`)) {
            router.delete(`/facilities/${f.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Fasilitas Lab" />
            <div className="space-y-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Fasilitas Lab</h1>
                    <p className="text-sm text-muted-foreground">
                        Daftar apa saja yang ada di laboratorium. Isi bebas sesuai kondisi lab, misalnya PC, TV, bangku, atau meja.
                    </p>
                </div>

                <div className="grid gap-1">
                    <Label htmlFor="lab">Laboratorium</Label>
                    <select
                        id="lab"
                        className={selectClass}
                        value={selectedLabId ?? ''}
                        onChange={(e) => changeLab(e.target.value)}
                    >
                        {labs.map((l) => (
                            <option key={l.id} value={l.id}>{l.name}</option>
                        ))}
                    </select>
                </div>

                {canManage && (
                    <form
                        onSubmit={submit}
                        className="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-[1fr_120px_140px_1fr_auto] md:items-start dark:border-sidebar-border"
                    >
                        <div className="grid gap-1">
                            <Label htmlFor="name">Nama fasilitas</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="quantity">Jumlah</Label>
                            <Input id="quantity" type="number" min={0} value={data.quantity} onChange={(e) => setData('quantity', e.target.value)} />
                            <InputError message={errors.quantity} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="unit">Satuan</Label>
                            <Input id="unit" placeholder="unit, buah, set" value={data.unit} onChange={(e) => setData('unit', e.target.value)} />
                            <InputError message={errors.unit} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="note">Catatan</Label>
                            <Input id="note" value={data.note} onChange={(e) => setData('note', e.target.value)} />
                            <InputError message={errors.note} />
                        </div>
                        <div className="flex gap-2 md:mt-6">
                            <Button type="submit" disabled={processing}>{editing ? 'Simpan' : 'Tambah'}</Button>
                            {editing && <Button type="button" variant="outline" onClick={cancel}>Batal</Button>}
                        </div>
                    </form>
                )}

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="p-3">Fasilitas</th>
                                <th className="p-3">Jumlah</th>
                                <th className="p-3">Satuan</th>
                                <th className="p-3">Catatan</th>
                                {canManage && <th className="p-3">Aksi</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {facilities.length === 0 && (
                                <tr>
                                    <td className="p-3" colSpan={canManage ? 5 : 4}>Belum ada fasilitas yang dicatat.</td>
                                </tr>
                            )}
                            {facilities.map((f) => (
                                <tr key={f.id} className="border-b last:border-0">
                                    <td className="p-3">{f.name}</td>
                                    <td className="p-3">{f.quantity}</td>
                                    <td className="p-3">{f.unit ?? '-'}</td>
                                    <td className="p-3">{f.note ?? '-'}</td>
                                    {canManage && (
                                        <td className="flex gap-2 p-3">
                                            <Button variant="outline" size="sm" onClick={() => startEdit(f)}>Ubah</Button>
                                            <Button variant="outline" size="sm" onClick={() => remove(f)}>Hapus</Button>
                                        </td>
                                    )}
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
    breadcrumbs: [{ title: 'Fasilitas Lab', href: '/facilities' }],
};
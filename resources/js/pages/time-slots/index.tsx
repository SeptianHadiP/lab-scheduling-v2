import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Slot = { id: number; name: string; start_time: string; end_time: string; is_active: boolean };

function SlotRow({ slot }: { slot: Slot }) {
    const { data, setData, put, processing, errors } = useForm({
        name: slot.name,
        start_time: slot.start_time,
        end_time: slot.end_time,
        is_active: slot.is_active,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(`/time-slots/${slot.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid items-start gap-3 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-[1fr_1fr_1fr_auto_auto] dark:border-sidebar-border">
            <div className="grid gap-1">
                <Label htmlFor={`name-${slot.id}`}>Nama</Label>
                <Input id={`name-${slot.id}`} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`start-${slot.id}`}>Mulai</Label>
                <Input id={`start-${slot.id}`} type="time" step={900} value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} />
                <InputError message={errors.start_time} />
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`end-${slot.id}`}>Selesai</Label>
                <Input id={`end-${slot.id}`} type="time" step={900} value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} />
                <InputError message={errors.end_time} />
            </div>
            <label className="flex items-center gap-2 text-sm md:mt-7">
                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                Aktif
            </label>
            <Button type="submit" disabled={processing} className="md:mt-6">Simpan</Button>
        </form>
    );
}

export default function Index({ slots }: { slots: Slot[] }) {
    return (
        <>
            <Head title="Slot Waktu" />
            <div className="space-y-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Slot Waktu</h1>
                    <p className="text-sm text-muted-foreground">
                        Empat blok jam tetap. Jam harus kelipatan 15 menit dan tidak boleh tumpang tindih. Perubahan hanya berlaku untuk pengajuan berikutnya.
                    </p>
                </div>
                {slots.map((s) => (
                    <SlotRow key={s.id} slot={s} />
                ))}
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: 'Slot Waktu', href: '/time-slots' }],
};
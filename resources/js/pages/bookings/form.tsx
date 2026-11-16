import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Slot = { id: number; name: string; start_time: string; end_time: string };
type Party = 'self' | 'user' | 'external';
type UserOption = { id: number; name: string; role_label: string };

type SessionRow = {
    id: number | null;
    slot: string;
    date: string;
    start_time: string;
    end_time: string;
};

type ExistingBooking = {
    id: number;
    lab_id: number;
    title: string;
    tools_software: string;
    party: Party;
    booked_for_user_id: number | null;
    external_name: string;
    external_contact: string;
    grading_enabled: boolean;
    certificate_enabled: boolean;
    certificate_title: string;
    sessions: { id: number; date: string; start_time: string; end_time: string }[];
};

const MAX_SESSIONS = 16;

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs';

export default function BookingForm({
    reservation,
    labs,
    slots,
    users,
    canBookForOthers,
    defaultLabId,
}: {
    reservation: ExistingBooking | null;
    labs: { id: number; name: string }[];
    slots: Slot[];
    users: UserOption[];
    canBookForOthers: boolean;
    defaultLabId: number | null;
}) {
    const isEdit = reservation !== null;
    const [query, setQuery] = useState('');

    const slotFor = (start: string, end: string) =>
        String(slots.find((s) => s.start_time === start && s.end_time === end)?.id ?? 'custom');

    const emptySession = (): SessionRow => ({ id: null, slot: '', date: '', start_time: '', end_time: '' });

    const { data, setData, post, put, processing, errors } = useForm({
        lab_id: String(reservation?.lab_id ?? defaultLabId ?? ''),
        title: reservation?.title ?? '',
        tools_software: reservation?.tools_software ?? '',
        party: (reservation?.party ?? 'self') as Party,
        booked_for_user_id: reservation?.booked_for_user_id ? String(reservation.booked_for_user_id) : '',
        external_name: reservation?.external_name ?? '',
        external_contact: reservation?.external_contact ?? '',
        grading_enabled: reservation?.grading_enabled ?? false,
        certificate_enabled: reservation?.certificate_enabled ?? false,
        certificate_title: reservation?.certificate_title ?? '',
        sessions: (reservation?.sessions.length
            ? reservation.sessions.map((s) => ({
                  id: s.id,
                  slot: slotFor(s.start_time, s.end_time),
                  date: s.date,
                  start_time: s.start_time,
                  end_time: s.end_time,
              }))
            : [emptySession()]) as SessionRow[],
    });

    const err = (key: string) => (errors as Record<string, string>)[key];

    const setSession = (i: number, patch: Partial<SessionRow>) =>
        setData('sessions', data.sessions.map((s, idx) => (idx === i ? { ...s, ...patch } : s)));

    const chooseSlot = (i: number, value: string) => {
        const slot = slots.find((s) => String(s.id) === value);

        if (slot) {
            setSession(i, { slot: value, start_time: slot.start_time, end_time: slot.end_time });
        } else {
            setSession(i, { slot: value });
        }
    };

    const addSession = () => setData('sessions', [...data.sessions, emptySession()]);

    const removeSession = (i: number) =>
        setData('sessions', data.sessions.filter((_, idx) => idx !== i));

    const visibleUsers = users.filter(
        (u) => u.name.toLowerCase().includes(query.toLowerCase()) || String(u.id) === data.booked_for_user_id,
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (reservation) {
            put(`/bookings/${reservation.id}`);
        } else {
            post('/bookings');
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Booking' : 'Booking Laboratorium'} />
            <form onSubmit={submit} className="max-w-3xl space-y-5 p-4">
                <div>
                    <h1 className="text-xl font-semibold">{isEdit ? 'Ubah Booking' : 'Booking Laboratorium'}</h1>
                    <p className="text-sm text-muted-foreground">
                        Untuk pemakaian lab di luar jadwal praktikum, misalnya kegiatan komunitas, rapat, atau kuliah tambahan.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-1">
                        <Label htmlFor="lab_id">Laboratorium</Label>
                        <select id="lab_id" className={selectClass} value={data.lab_id} onChange={(e) => setData('lab_id', e.target.value)}>
                            <option value="">Pilih laboratorium</option>
                            {labs.map((l) => (
                                <option key={l.id} value={l.id}>{l.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.lab_id} />
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="title">Judul kegiatan</Label>
                        <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        <InputError message={errors.title} />
                    </div>
                </div>

                <div className="space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="grid gap-1">
                        <Label htmlFor="party">Booking untuk</Label>
                        <select
                            id="party"
                            className={selectClass}
                            value={data.party}
                            onChange={(e) => setData('party', e.target.value as Party)}
                        >
                            <option value="self">Diri sendiri</option>
                            {canBookForOthers && <option value="user">Pengguna terdaftar</option>}
                            {canBookForOthers && <option value="external">Pihak eksternal (tanpa akun)</option>}
                        </select>
                        <InputError message={errors.party} />
                    </div>

                    {data.party === 'user' && (
                        <div className="grid gap-2">
                            <Label htmlFor="user_search">Cari pengguna</Label>
                            <Input
                                id="user_search"
                                placeholder="Ketik nama"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                            <select
                                className={selectClass}
                                value={data.booked_for_user_id}
                                onChange={(e) => setData('booked_for_user_id', e.target.value)}
                            >
                                <option value="">Pilih pengguna</option>
                                {visibleUsers.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name} ({u.role_label})
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.booked_for_user_id} />
                        </div>
                    )}

                    {data.party === 'external' && (
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="grid gap-1">
                                <Label htmlFor="external_name">Nama pihak eksternal</Label>
                                <Input
                                    id="external_name"
                                    value={data.external_name}
                                    onChange={(e) => setData('external_name', e.target.value)}
                                />
                                <InputError message={errors.external_name} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="external_contact">Kontak (email atau telepon)</Label>
                                <Input
                                    id="external_contact"
                                    value={data.external_contact}
                                    onChange={(e) => setData('external_contact', e.target.value)}
                                />
                                <InputError message={errors.external_contact} />
                            </div>
                        </div>
                    )}
                </div>

                <div className="grid gap-1">
                    <Label htmlFor="tools_software">Tools atau software yang dibutuhkan (opsional)</Label>
                    <Input
                        id="tools_software"
                        value={data.tools_software}
                        onChange={(e) => setData('tools_software', e.target.value)}
                    />
                    <InputError message={errors.tools_software} />
                </div>

                <div className="space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.grading_enabled}
                            onChange={(e) => {
                                setData((d) => ({
                                    ...d,
                                    grading_enabled: e.target.checked,
                                    certificate_enabled: e.target.checked ? d.certificate_enabled : false,
                                }));
                            }}
                        />
                        Kegiatan ini memiliki nilai
                    </label>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            disabled={!data.grading_enabled}
                            checked={data.certificate_enabled}
                            onChange={(e) => setData('certificate_enabled', e.target.checked)}
                        />
                        Terbitkan sertifikat (hanya jika ada nilai)
                    </label>
                    <InputError message={errors.certificate_enabled} />

                    {data.certificate_enabled && (
                        <div className="grid gap-1">
                            <Label htmlFor="certificate_title">Judul materi pada sertifikat</Label>
                            <Input
                                id="certificate_title"
                                value={data.certificate_title}
                                onChange={(e) => setData('certificate_title', e.target.value)}
                            />
                            <InputError message={errors.certificate_title} />
                        </div>
                    )}

                    {data.grading_enabled && (
                        <p className="text-xs text-muted-foreground">
                            Daftar peserta ditambahkan setelah booking disetujui (fitur menyusul).
                        </p>
                    )}
                </div>

                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="font-medium">Pertemuan ({data.sessions.length})</h2>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={data.sessions.length >= MAX_SESSIONS}
                            onClick={addSession}
                        >
                            <Plus /> Tambah pertemuan
                        </Button>
                    </div>
                    <InputError message={err('sessions')} />

                    {data.sessions.map((s, i) => (
                        <div key={i} className="space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium">Pertemuan {i + 1}</span>
                                {data.sessions.length > 1 && (
                                    <Button type="button" variant="ghost" size="sm" onClick={() => removeSession(i)}>
                                        <Trash2 /> Hapus
                                    </Button>
                                )}
                            </div>

                            <div className="grid gap-3 md:grid-cols-3">
                                <div className="grid gap-1">
                                    <Label htmlFor={`date-${i}`}>Tanggal</Label>
                                    <Input id={`date-${i}`} type="date" value={s.date} onChange={(e) => setSession(i, { date: e.target.value })} />
                                    <InputError message={err(`sessions.${i}.date`)} />
                                </div>

                                <div className="grid gap-1 md:col-span-2">
                                    <Label htmlFor={`slot-${i}`}>Sesi</Label>
                                    <select
                                        id={`slot-${i}`}
                                        className={selectClass}
                                        value={s.slot}
                                        onChange={(e) => chooseSlot(i, e.target.value)}
                                    >
                                        <option value="">Pilih sesi</option>
                                        {slots.map((sl) => (
                                            <option key={sl.id} value={sl.id}>
                                                {sl.name} ({sl.start_time}-{sl.end_time})
                                            </option>
                                        ))}
                                        <option value="custom">Jam kustom</option>
                                    </select>
                                </div>
                            </div>

                            {s.slot === 'custom' && (
                                <div className="grid gap-3 md:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor={`start-${i}`}>Mulai</Label>
                                        <Input
                                            id={`start-${i}`}
                                            type="time"
                                            step={900}
                                            value={s.start_time}
                                            onChange={(e) => setSession(i, { start_time: e.target.value })}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor={`end-${i}`}>Selesai</Label>
                                        <Input
                                            id={`end-${i}`}
                                            type="time"
                                            step={900}
                                            value={s.end_time}
                                            onChange={(e) => setSession(i, { end_time: e.target.value })}
                                        />
                                    </div>
                                </div>
                            )}
                            <InputError message={err(`sessions.${i}.start_time`)} />
                            <InputError message={err(`sessions.${i}.end_time`)} />
                        </div>
                    ))}
                </div>

                <div className="flex gap-2">
                    <Button type="submit" disabled={processing}>Simpan Draft</Button>
                    <Button asChild variant="outline">
                        <Link href={isEdit ? `/reservations/${reservation.id}` : '/reservations'}>Batal</Link>
                    </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                    Slot baru ditahan saat booking dikirim, bukan saat draft disimpan.
                </p>
            </form>
        </>
    );
}

BookingForm.layout = {
    breadcrumbs: [
        { title: 'Pengajuan Jadwal', href: '/reservations' },
        { title: 'Booking', href: '#' },
    ],
};
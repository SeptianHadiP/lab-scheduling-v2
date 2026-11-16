import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Slot = { id: number; name: string; start_time: string; end_time: string };

type SessionRow = {
    id: number | null;
    slot: string;
    date: string;
    start_time: string;
    end_time: string;
    module: File | null;
    has_module: boolean;
};

type ExistingReservation = {
    id: number;
    lab_id: number;
    course_class_id: number;
    tools_software: string;
    grading_enabled: boolean;
    certificate_enabled: boolean;
    certificate_title: string;
    sessions: { id: number; date: string; start_time: string; end_time: string; has_module: boolean }[];
};

const MAX_SESSIONS = 16;

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs';

export default function ReservationForm({
    reservation,
    classes,
    labs,
    slots,
    defaultLabId,
}: {
    reservation: ExistingReservation | null;
    classes: { id: number; label: string }[];
    labs: { id: number; name: string }[];
    slots: Slot[];
    defaultLabId: number | null;
}) {
    const isEdit = reservation !== null;

    const slotFor = (start: string, end: string) =>
        String(slots.find((s) => s.start_time === start && s.end_time === end)?.id ?? 'custom');

    const emptySession = (): SessionRow => ({
        id: null,
        slot: '',
        date: '',
        start_time: '',
        end_time: '',
        module: null,
        has_module: false,
    });

    const { data, setData, post, transform, processing, errors } = useForm({
        lab_id: String(reservation?.lab_id ?? defaultLabId ?? ''),
        course_class_id: String(reservation?.course_class_id ?? ''),
        tools_software: reservation?.tools_software ?? '',
        grading_enabled: reservation?.grading_enabled ?? true,
        certificate_enabled: reservation?.certificate_enabled ?? false,
        certificate_title: reservation?.certificate_title ?? '',
        sessions: (reservation?.sessions.length
            ? reservation.sessions.map((s) => ({
                  id: s.id,
                  slot: slotFor(s.start_time, s.end_time),
                  date: s.date,
                  start_time: s.start_time,
                  end_time: s.end_time,
                  module: null,
                  has_module: s.has_module,
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

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (reservation) {
            // Unggah berkas tidak bisa memakai PUT langsung, jadi memakai POST dengan _method.
            transform((d) => ({ ...d, _method: 'put' }));
            post(`/reservations/${reservation.id}`, { forceFormData: true });
        } else {
            post('/reservations', { forceFormData: true });
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Ubah Pengajuan' : 'Ajukan Jadwal Praktikum'} />
            <form onSubmit={submit} className="max-w-3xl space-y-5 p-4">
                <h1 className="text-xl font-semibold">{isEdit ? 'Ubah Pengajuan' : 'Ajukan Jadwal Praktikum'}</h1>

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
                        <Label htmlFor="course_class_id">Mata kuliah dan kelas</Label>
                        <select
                            id="course_class_id"
                            className={selectClass}
                            value={data.course_class_id}
                            onChange={(e) => setData('course_class_id', e.target.value)}
                        >
                            <option value="">Pilih kelas</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>{c.label}</option>
                            ))}
                        </select>
                        <InputError message={errors.course_class_id} />
                    </div>
                </div>

                <div className="grid gap-1">
                    <Label htmlFor="tools_software">Tools atau software yang digunakan</Label>
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
                        Praktikum ini memiliki nilai
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

                            <div className="grid gap-1">
                                <Label htmlFor={`module-${i}`}>
                                    Modul praktikum (PDF, DOC, DOCX, PPT, PPTX; maks. 10 MB)
                                </Label>
                                <Input
                                    id={`module-${i}`}
                                    type="file"
                                    accept=".pdf,.doc,.docx,.ppt,.pptx"
                                    onChange={(e) => setSession(i, { module: e.target.files?.[0] ?? null })}
                                />
                                {s.has_module && !s.module && (
                                    <p className="text-xs text-muted-foreground">
                                        Modul sudah diunggah. Pilih berkas baru hanya jika ingin menggantinya.
                                    </p>
                                )}
                                <InputError message={err(`sessions.${i}.module`)} />
                            </div>
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
                    Slot baru ditahan saat pengajuan dikirim, bukan saat draft disimpan.
                </p>
            </form>
        </>
    );
}

ReservationForm.layout = {
    breadcrumbs: [
        { title: 'Pengajuan Jadwal', href: '/reservations' },
        { title: 'Form', href: '#' },
    ],
};
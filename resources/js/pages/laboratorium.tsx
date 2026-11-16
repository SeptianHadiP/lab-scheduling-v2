import PublicSiteHeader from '@/components/public-site-header';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight, BrainCircuit, CheckCircle2, Code2,
    FlaskConical, GraduationCap, MapPin, Network, ShieldCheck, X,
} from 'lucide-react';
import { dashboard, login } from '@/routes';
import { useState } from 'react';

type Facility = { id: number; name: string; quantity: number; unit: string | null; note: string | null };
type Lab = { id: number; name: string; code: string; facilities: Facility[]; head: string | null; assistants: string[] };
type Props = { labs: Lab[] };

const labDetails = [
    {
        name: 'Laboratorium Data Science', location: 'SS-409', category: 'DATA & ANALITIKA',
        description: 'Ruang untuk mengolah data, membangun model analitik, dan mengeksplorasi wawasan berbasis data melalui eksperimen komputasi.',
        focus: ['Data analytics', 'Machine learning', 'Visualisasi data'], icon: BrainCircuit,
        image: 'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Pemograman-1.jpg',
        gallery: [
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Pemograman-1.jpg',
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Pemograman-2.jpg',
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Pemograman-3-1.jpg',
        ],
        // source: 'https://lab.fasilkom.ubharajaya.ac.id/laboratorium/data-science/',
    },
    {
        name: 'Laboratorium Software Development', location: 'SS-215', category: 'REKAYASA PERANGKAT LUNAK',
        description: 'Lingkungan praktik untuk merancang, mengembangkan, menguji, dan mengevaluasi aplikasi serta solusi perangkat lunak.',
        focus: ['Web & application development', 'Software engineering', 'Quality assurance'], icon: Code2,
        image: 'https://ubharajaya.ac.id/wp-content/uploads/2024/08/LAB-KOMPUTER-DASAR-4.jpg',
        gallery: [
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/LAB-KOMPUTER-DASAR-4.jpg',
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/LAB-KOMPUTER-DASAR-3-1.jpg',
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Pemograman-1.jpg',
        ],
        // source: 'https://lab.fasilkom.ubharajaya.ac.id/laboratorium/software-development/',
    },
    {
        name: 'Laboratorium Network dan Cyber Security', location: 'SS-421', category: 'JARINGAN & KEAMANAN',
        description: 'Ruang pembelajaran jaringan komputer dan keamanan siber untuk memahami infrastruktur, konfigurasi jaringan, serta praktik pengamanan sistem.',
        focus: ['Computer networking', 'Cyber security', 'Infrastructure lab'], icon: Network,
        image: 'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Jaringan-2.jpg',
        gallery: [
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Jaringan-2.jpg',
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Pemograman-2.jpg',
            'https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Pemograman-3-1.jpg',
        ],
        // source: 'https://lab.fasilkom.ubharajaya.ac.id/laboratorium/network-and-cyber-security/',
    },
];

const learningAreas = [
    { icon: BrainCircuit, title: 'Data & kecerdasan buatan', text: 'Eksplorasi data, pemodelan, machine learning, dan visualisasi informasi.' },
    { icon: Code2, title: 'Pengembangan perangkat lunak', text: 'Perancangan aplikasi, implementasi fitur, pengujian, dan kolaborasi pengembangan.' },
    { icon: ShieldCheck, title: 'Jaringan & keamanan siber', text: 'Pemahaman jaringan, infrastruktur, serta prinsip keamanan sistem informasi.' },
];

export default function Laboratorium({ labs }: Props) {
    const { auth } = usePage().props;
    const [selectedLab, setSelectedLab] = useState<number | null>(null);

    const findLab = (name: string) => {
        const normalize = (value: string) => value.toLowerCase().replace(/laboratorium komputer/g, 'laboratorium').replace(/\bdan\b/g, '&').replace(/\s+/g, ' ').trim();
        const target = normalize(name);
        return labs.find((lab) => normalize(lab.name) === target || normalize(lab.name).includes(target.replace('laboratorium ', '')));
    };

    return (
        <>
            <Head title="Laboratorium Fasilkom | Lab Scheduling Ubhara Jaya" />
            <main className="welcome-page laboratory-page">
                <PublicSiteHeader active="laboratorium" />

                <section className="laboratory-hero">
                    <div className="laboratory-hero-content">
                        <span className="lab-eyebrow"><span /> FAKULTAS ILMU KOMPUTER</span>
                        <h1>Ruang untuk <span>bereksplorasi.</span><br />Tempat ide jadi nyata.</h1>
                        <p>Kenali laboratorium Fasilkom Universitas Bhayangkara Jakarta Raya—ruang untuk belajar, membangun solusi digital, dan mengembangkan keterampilan teknologi.</p>
                        <a className="lab-hero-link" href="#daftar-lab">Jelajahi laboratorium <ArrowRight size={18} /></a>
                    </div>
                    <div className="laboratory-hero-visual">
                        <img src="https://ubharajaya.ac.id/wp-content/uploads/2024/08/Komputer-Pemograman-1.jpg" alt="Aktivitas pembelajaran di laboratorium komputer Ubhara Jaya" />
                        <div className="lab-image-label"><FlaskConical size={18} /><span><b>Laboratorium Fasilkom</b><small>Belajar · Praktik · Berkarya</small></span></div>
                        <div className="lab-floating-mark"><Code2 size={28} /></div>
                    </div>
                    <div className="lab-hero-orb orb-a" /><div className="lab-hero-orb orb-b" />
                </section>

                <section className="lab-intro-strip">
                    <div><span className="lab-strip-number">01</span><p><b>Eksplorasi teknologi</b><small>Belajar melalui praktik dan eksperimen</small></p></div>
                    <div><span className="lab-strip-number">02</span><p><b>Kolaborasi akademik</b><small>Mendukung kegiatan mahasiswa dan dosen</small></p></div>
                    <div><span className="lab-strip-number">03</span><p><b>Siap menghadapi tantangan</b><small>Mengembangkan kompetensi digital</small></p></div>
                </section>

                <section className="laboratory-list-section" id="daftar-lab">
                    <div className="lab-section-heading">
                        <span className="lab-section-kicker">LABORATORIUM FASILKOM</span>
                        <h2>Temukan ruang <span>belajarmu.</span></h2>
                        <p>Tiga bidang utama yang saling melengkapi dalam ekosistem ilmu komputer.</p>
                    </div>
                    <div className="lab-cards">
                        {labDetails.map((detail, index) => {
                            const Icon = detail.icon;
                            const data = findLab(detail.name);

                            return (
                                <article className="lab-card" key={detail.name}>
                                    <div className="lab-card-photo-wrap">
                                        <img className="lab-card-photo" src={detail.image} alt={`Fasilitas ${detail.name}`} loading="lazy" />
                                        <span className="lab-card-number">0{index + 1}</span>
                                        <span className="lab-card-category">{detail.category}</span>
                                    </div>
                                    <div className="lab-card-body">
                                        <span className="lab-card-icon"><Icon size={23} /></span>
                                        <h3>{detail.name}</h3>
                                        <p className="lab-card-description">{detail.description}</p>
                                        <div className="lab-card-location"><MapPin size={16} /><span>Lokasi <b>{detail.location}</b></span></div>
                                        <div className="lab-card-people">
                                            <div><span>Kepala Lab</span><b>{data?.head || 'Informasi belum tersedia'}</b></div>
                                            <div><span>Asisten Lab</span><b>{data?.assistants.length ? data.assistants.join(', ') : 'Informasi belum tersedia'}</b></div>
                                        </div>
                                        <button className="lab-detail-button" type="button" onClick={() => setSelectedLab(index)}>Lihat fasilitas lab <ArrowRight size={17} /></button>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                </section>

                {selectedLab !== null && (() => {
                    const detail = labDetails[selectedLab];
                    const data = findLab(detail.name);
                    return (
                        <div className="lab-modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelectedLab(null); }}>
                            <section className="lab-facility-modal" role="dialog" aria-modal="true" aria-labelledby="lab-modal-title">
                                <header className="lab-modal-header">
                                    <div><span className="lab-section-kicker">FASILITAS LABORATORIUM · {detail.location}</span><h2 id="lab-modal-title">{detail.name}</h2><p>{detail.description}</p></div>
                                    <button className="lab-modal-close" type="button" aria-label="Tutup detail fasilitas" onClick={() => setSelectedLab(null)}><X size={22} /></button>
                                </header>
                                <div className="lab-modal-content">
                                    <div className="lab-modal-facilities"><h3><CheckCircle2 size={19} /> Fasilitas yang tersedia</h3>
                                        {data?.facilities.length ? <ul className="lab-facility-list">{data.facilities.map((facility) => <li key={facility.id}><CheckCircle2 size={16} /><span>{facility.name}{facility.quantity > 0 ? ` · ${facility.quantity}${facility.unit ? ` ${facility.unit}` : ''}` : ''}{facility.note ? <small>{facility.note}</small> : null}</span></li>)}</ul> : <p className="lab-no-facility">Data fasilitas belum ditambahkan pada sistem. Foto di bawah merupakan dokumentasi fasilitas laboratorium komputer Fasilkom dari situs resmi universitas.</p>}
                                        <div className="lab-modal-contact"><div><span>Kepala Laboratorium</span><b>{data?.head || 'Informasi belum tersedia'}</b></div><div><span>Asisten Laboratorium</span><b>{data?.assistants.length ? data.assistants.join(', ') : 'Informasi belum tersedia'}</b></div></div>
                                    </div>
                                    <div className="lab-modal-gallery-section"><div className="lab-gallery-heading"><h3>Galeri fasilitas</h3><span>{detail.gallery.length} foto</span></div><div className="lab-modal-gallery">{detail.gallery.map((image, imageNumber) => <a key={image} href={image} target="_blank" rel="noreferrer" className="lab-modal-gallery-item"><img src={image} alt={`Foto ${imageNumber + 1} ${detail.name}`} loading="lazy" /><span>Foto {imageNumber + 1}</span></a>)}</div><p className="lab-image-credit">Sumber foto: <a href="https://ubharajaya.ac.id/fasilitas-infrastruktur/" target="_blank" rel="noreferrer">Fasilitas Infrastruktur Ubhara Jaya</a></p></div>
                                    {/* <a className="lab-official-link" href={detail.source} target="_blank" rel="noreferrer">Kunjungi halaman resmi laboratorium <ArrowRight size={16} /></a> */}
                                </div>
                            </section>
                        </div>
                    );
                })()}

                <section className="lab-learning-section">
                    <div className="lab-learning-heading"><span className="lab-section-kicker">EKOSISTEM PEMBELAJARAN</span><h2>Dari konsep ke <span>implementasi.</span></h2><p>Laboratorium mendukung berbagai pendekatan pembelajaran di bidang komputasi.</p></div>
                    <div className="lab-learning-grid">{learningAreas.map(({ icon: Icon, title, text }, index) => <article className="lab-learning-card" key={title}><span className={`lab-learning-icon learning-${index + 1}`}><Icon size={23} /></span><h3>{title}</h3><p>{text}</p><span className="lab-learning-index">0{index + 1}</span></article>)}</div>
                </section>

                <section className="lab-cta-section">
                    <div className="lab-cta-icon"><GraduationCap size={29} /></div><div className="lab-cta-copy"><span>LANGKAH SELANJUTNYA</span><h2>Siapkan kegiatan praktikum dengan lebih terencana.</h2><p>Akses Lab Scheduling untuk melihat layanan dan melanjutkan kebutuhan akademik Anda.</p></div>
                    <Link className="button-primary lab-cta-button" href={auth.user ? dashboard() : login()}>{auth.user ? 'Buka Dashboard' : 'Masuk ke Sistem'} <ArrowRight size={18} /></Link>
                </section>
                <footer className="welcome-footer"><div className="footer-brand"><span className="footer-dot" /> Lab Scheduling <span>·</span> Universitas Bhayangkara Jakarta Raya</div><p>Platform pendukung kegiatan praktikum, pembelajaran, dan penelitian.</p></footer>
            </main>
        </>
    );
}

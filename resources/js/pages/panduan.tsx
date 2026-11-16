import PublicSiteHeader from '@/components/public-site-header';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownToLine, ArrowRight, BookOpen, CheckCircle2, ChevronRight,
    Code2, Database, ExternalLink, FlaskConical, Laptop, ShieldCheck,
} from 'lucide-react';
import './panduan.css';

type Props = { section?: 'sop' | 'software' };

const softwareGroups = [
    {
        title: 'Data Science',
        description: 'Perangkat untuk analisis data, eksplorasi statistik, dan pemodelan.',
        icon: Database,
        items: [
            { name: 'Orange Data Mining', url: 'https://orangedatamining.com/' },
            { name: 'WEKA', url: 'https://sourceforge.net/projects/weka/' },
            { name: 'PSPP', url: 'https://www.gnu.org/software/pspp/get.html' },
            { name: 'Anaconda', url: 'https://www.anaconda.com/download' },
        ],
    },
    {
        title: 'Software Development',
        description: 'Perangkat untuk pemrograman, pengembangan aplikasi, dan perancangan sistem.',
        icon: Code2,
        items: [
            { name: 'Visual Studio Code', url: 'https://code.visualstudio.com/download' },
            { name: 'Android Studio', url: 'https://developer.android.com/studio' },
            { name: 'Project Libre', url: 'https://www.projectlibre.com/product/1-alternative-microsoft-project-free-project-management-software-open-source' },
            { name: 'Logisim Evolution', url: 'https://github.com/logisim-evolution/logisim-evolution' },
            { name: 'VirtualBox', url: 'https://www.virtualbox.org/wiki/Downloads' },
            { name: 'Laragon', url: 'https://laragon.org/download/', recommended: true },
            { name: 'XAMPP', url: 'https://www.apachefriends.org/download.html', note: 'Tidak disarankan. Silakan gunakan Laragon.', securityUrl: 'https://github.com/Neustradamus/xampp' },
        ],
    },
    {
        title: 'Network & Cyber Security',
        description: 'Perangkat untuk simulasi jaringan, analisis trafik, dan keamanan sistem.',
        icon: ShieldCheck,
        items: [
            { name: 'Cisco Packet Tracer', url: 'https://www.netacad.com/cisco-packet-tracer' },
            { name: 'Wireshark', url: 'https://www.wireshark.org/download.html' },
            { name: 'GNS3', url: 'https://www.gns3.com/' },
            { name: 'Wazuh Agent', url: 'https://documentation.wazuh.com/current/installation-guide/wazuh-agent/index.html' },
        ],
    },
];

export default function Panduan({ section = 'sop' }: Props) {
    const isSoftware = section === 'software';

    return (
        <>
            <Head title={`${isSoftware ? 'Software Pendukung' : 'SOP & Tata Tertib'} | Panduan Lab Scheduling`} />
            <main className="guide-page">
                <PublicSiteHeader active="panduan" />
                <section className="guide-hero">
                    <div className="guide-hero-glow guide-glow-one" />
                    <div className="guide-hero-glow guide-glow-two" />
                    <div className="guide-hero-copy">
                        <span className="guide-eyebrow"><BookOpen size={15} /> PUSAT INFORMASI LABORATORIUM</span>
                        <h1>Panduan <span>{isSoftware ? 'Software Pendukung' : 'Laboratorium'}</span></h1>
                        <p>
                            {isSoftware
                                ? 'Temukan perangkat lunak pendukung kegiatan praktikum dan pengembangan kompetensi akademik.'
                                : 'Pahami tata tertib dan prosedur penggunaan laboratorium agar kegiatan praktikum berjalan aman, tertib, dan nyaman.'}
                        </p>
                        <div className="guide-hero-meta"><span><CheckCircle2 size={15} /> Informasi resmi penggunaan laboratorium</span><span><FlaskConical size={15} /> Universitas Bhayangkara Jakarta Raya</span></div>
                    </div>
                    <div className="guide-hero-art" aria-hidden="true">
                        <div className="guide-orbit guide-orbit-one" />
                        <div className="guide-orbit guide-orbit-two" />
                        <div className="guide-art-icon">{isSoftware ? <Laptop size={82} strokeWidth={1.2} /> : <BookOpen size={82} strokeWidth={1.2} />}</div>
                        <div className="guide-art-caption"><span /> {isSoftware ? 'Software untuk kegiatan akademik' : 'Panduan penggunaan laboratorium'}</div>
                    </div>
                </section>

                <section className="guide-body">
                    <aside className="guide-sidebar">
                        <span className="guide-kicker">MENU PANDUAN</span>
                        <h2>Informasi yang kamu butuhkan</h2>
                        <Link href="/panduan" className={`guide-side-link${!isSoftware ? ' is-active' : ''}`}>
                            <span className="guide-side-icon"><ShieldCheck size={18} /></span>
                            <span><strong>SOP &amp; Tata Tertib</strong><small>Aturan dan langkah penggunaan</small></span>
                            <ChevronRight size={17} />
                        </Link>
                        <Link href="/panduan/software" className={`guide-side-link${isSoftware ? ' is-active' : ''}`}>
                            <span className="guide-side-icon"><Code2 size={18} /></span>
                            <span><strong>Software Pendukung</strong><small>Daftar perangkat lunak</small></span>
                            <ChevronRight size={17} />
                        </Link>
                        <div className="guide-help-card">
                            <span className="guide-help-icon"><FlaskConical size={19} /></span>
                            <strong>Gunakan fasilitas dengan bijak</strong>
                            <p>Ikuti arahan laboran dan dosen untuk menjaga keamanan serta kenyamanan bersama.</p>
                        </div>
                    </aside>

                    <div className="guide-main-content">
                        {!isSoftware ? (
                            <>
                                <div className="guide-section-heading">
                                    <span className="guide-kicker">PANDUAN PENGGUNA</span>
                                    <h2>SOP &amp; Tata Tertib</h2>
                                    <p>Ketentuan dasar yang perlu dipatuhi seluruh pengguna Laboratorium Komputer.</p>
                                </div>
                                <article className="guide-document-card">
                                    <div className="guide-card-heading">
                                        <span className="guide-card-icon mint"><ShieldCheck size={22} /></span>
                                        <div><span className="guide-card-label">01 / KETENTUAN</span><h3>Tata Tertib Laboratorium Komputer</h3></div>
                                    </div>
                                    <div className="guide-rules-list">
                                        <div className="guide-rule"><span>01</span><div><h4>Kebersihan &amp; Kesehatan</h4><p>Dilarang membawa makanan, minuman, atau merokok di dalam ruangan. Pengguna wajib melepas alas kaki jika diwajibkan oleh aturan setempat.</p></div></div>
                                        <div className="guide-rule"><span>02</span><div><h4>Penampilan &amp; Sikap</h4><p>Wajib berpakaian rapi dan sopan, menjaga ketenangan, serta tidak membuat kegaduhan yang mengganggu pengguna lain.</p></div></div>
                                        <div className="guide-rule"><span>03</span><div><h4>Keamanan Perangkat</h4><p>Dilarang mencabut kabel, memindahkan posisi perangkat (mouse, keyboard, monitor), atau membuka casing komputer tanpa izin petugas.</p></div></div>
                                        <div className="guide-rule"><span>04</span><div><h4>Sistem &amp; Software</h4><p>Dilarang menginstal aplikasi, mengubah pengaturan sistem, atau memasukkan data/program asing tanpa izin laboran atau dosen.</p></div></div>
                                        <div className="guide-rule"><span>05</span><div><h4>Sanksi Pelanggaran</h4><p>Pelanggaran terhadap tata tertib dapat berakibat pada teguran, pembatalan jadwal penggunaan, kewajiban ganti rugi atas kerusakan, atau sanksi akademik/administratif.</p></div></div>
                                    </div>
                                </article>

                                <article className="guide-document-card guide-sop-card">
                                    <div className="guide-card-heading">
                                        <span className="guide-card-icon violet"><BookOpen size={22} /></span>
                                        <div><span className="guide-card-label">02 / PROSEDUR</span><h3>Standard Operational Procedure (SOP) Penggunaan</h3></div>
                                    </div>
                                    <div className="guide-sop-steps">
                                        <section className="guide-sop-step"><span className="guide-step-number">1</span><div><h4>Persiapan &amp; Masuk</h4><ul><li>Hadir tepat waktu sesuai jadwal yang telah ditentukan.</li><li>Melakukan registrasi atau mengisi daftar hadir (absen) serta menunjukkan identitas/KTM jika diperlukan.</li><li>Memeriksa kondisi awal komputer dan melaporkan segera kepada petugas jika ada kerusakan.</li></ul></div></section>
                                        <section className="guide-sop-step"><span className="guide-step-number">2</span><div><h4>Pelaksanaan Praktikum / Kegiatan</h4><ul><li>Menggunakan komputer hanya untuk keperluan akademis atau pekerjaan resmi.</li><li>Menggunakan headset/earphone jika perlu mendengarkan audio atau memutar musik agar tidak mengganggu orang lain.</li></ul></div></section>
                                        <section className="guide-sop-step"><span className="guide-step-number">3</span><div><h4>Penyelesaian &amp; Keluar</h4><ul><li>Menyimpan seluruh data pekerjaan ke dalam penyimpanan eksternal atau cloud pribadi.</li><li>Melakukan log out akun dan mematikan (Shut Down) komputer sesuai prosedur resmi.</li><li>Merapikan kembali kursi, perangkat keras, dan membuang sampah pada tempatnya sebelum meninggalkan laboratorium.</li></ul></div></section>
                                    </div>
                                </article>
                            </>
                        ) : (
                            <>
                                <div className="guide-section-heading">
                                    <span className="guide-kicker">REFERENSI PERANGKAT LUNAK</span>
                                    <h2>Perangkat Lunak</h2>
                                    <p>Daftar software yang dapat mendukung kegiatan praktikum. Pilih nama software untuk membuka situs resminya atau halaman unduhan.</p>
                                </div>
                                <div className="guide-software-grid">
                                    {softwareGroups.map((group, index) => {
                                        const Icon = group.icon;
                                        return (
                                            <article className="guide-software-card" key={group.title}>
                                                <div className="guide-software-card-heading">
                                                    <span className={`guide-card-icon ${index === 0 ? 'mint' : index === 1 ? 'violet' : 'blue'}`}><Icon size={22} /></span>
                                                    <div><span className="guide-card-label">KATEGORI 0{index + 1}</span><h3>{group.title}</h3></div>
                                                </div>
                                                <p className="guide-software-description">{group.description}</p>
                                                <div className="guide-software-list">
                                                    {group.items.map((item) => (
                                                        <div className="guide-software-item" key={item.name}>
                                                            <div className="guide-software-item-copy">
                                                                <a href={item.url} target="_blank" rel="noreferrer">{item.name} <ExternalLink size={13} /></a>
                                                                {'recommended' in item && item.recommended && <span className="guide-recommended">Direkomendasikan</span>}
                                                                {'note' in item && item.note && <p className="guide-software-note">{item.note} {'securityUrl' in item && item.securityUrl && <a href={item.securityUrl} target="_blank" rel="noreferrer">Informasi keamanan <ExternalLink size={11} /></a>}</p>}
                                                            </div>
                                                            <a className="guide-download-link" href={item.url} target="_blank" rel="noreferrer" aria-label={`Buka halaman ${item.name}`}><ArrowDownToLine size={16} /></a>
                                                        </div>
                                                    ))}
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            </>
                        )}
                        <div className="guide-bottom-note">
                            <ShieldCheck size={18} />
                            <p><strong>Butuh informasi lebih lanjut?</strong> Hubungi laboran atau dosen pengampu untuk memastikan prosedur dan software yang sesuai dengan kebutuhan praktikum.</p>
                        </div>
                    </div>
                </section>
                <footer className="guide-footer"><span>Lab Scheduling</span><span>Universitas Bhayangkara Jakarta Raya</span><Link href="/">Kembali ke Beranda <ArrowRight size={14} /></Link></footer>
            </main>
        </>
    );
}

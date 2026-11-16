import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Clock3,
    FlaskConical,
    Info,
    KeyRound,
    Mail,
    ShieldCheck,
} from 'lucide-react';

import InputError from '@/components/input-error';
import { login } from '@/routes';
import { email } from '@/routes/password';

import './login.css';
import './forgot-password.css';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Lupa Kata Sandi | Lab Scheduling" />

            <main className="login-screen">
                {/* Panel kiri (desktop) */}
                <section
                    className="login-showcase"
                    aria-label="Informasi Lab Scheduling"
                >
                    <div className="login-showcase-orbit login-showcase-orbit-one" />
                    <div className="login-showcase-orbit login-showcase-orbit-two" />
                    <div className="login-showcase-glow" />

                    <Link
                        href="/"
                        className="login-brand"
                        aria-label="Lab Scheduling, kembali ke beranda"
                    >
                        <span className="login-brand-mark">
                            <FlaskConical size={31} strokeWidth={1.7} />
                        </span>

                        <span className="login-brand-university">
                            <strong>UNIVERSITAS BHAYANGKARA</strong>
                            <small>JAKARTA RAYA</small>
                        </span>

                        <span className="login-brand-divider" />

                        <span className="login-brand-product">
                            <b>Lab</b> Scheduling
                        </span>
                    </Link>

                    <div className="login-showcase-content">
                        <span className="login-eyebrow">
                            <span />
                            PEMULIHAN AKUN
                        </span>

                        <h1>
                            Lupa Kata Sandi?
                            <span>Kami Bantu Pulihkan</span>
                        </h1>

                        <p className="login-showcase-description">
                            Masukkan email yang terdaftar dan kami akan
                            mengirimkan tautan untuk mengatur ulang kata sandi
                            akun Lab Scheduling Anda.
                        </p>

                        <div className="login-benefits">
                            <div className="login-benefit">
                                <span className="login-benefit-icon">
                                    <Mail size={27} />
                                </span>
                                <div>
                                    <strong>Tautan dikirim ke email</strong>
                                    <p>
                                        Gunakan email yang sama dengan akun
                                        Anda.
                                    </p>
                                </div>
                            </div>

                            <div className="login-benefit">
                                <span className="login-benefit-icon">
                                    <Clock3 size={27} />
                                </span>
                                <div>
                                    <strong>Diterima dalam beberapa menit</strong>
                                    <p>
                                        Periksa folder spam jika email belum
                                        masuk.
                                    </p>
                                </div>
                            </div>

                            <div className="login-benefit">
                                <span className="login-benefit-icon">
                                    <ShieldCheck size={29} />
                                </span>
                                <div>
                                    <strong>Proses yang aman</strong>
                                    <p>
                                        Tautan reset hanya berlaku untuk akun
                                        Anda.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="login-showcase-foot">
                        <ShieldCheck size={19} />
                        <span>
                            Akses informasi laboratorium lebih praktis dan
                            terarah
                        </span>
                    </div>
                </section>

                {/* Panel kanan */}
                <section
                    className="login-panel"
                    aria-label="Form lupa kata sandi"
                >
                    <div className="login-panel-inner">
                        {/* Brand khusus mobile (di atas kartu) */}
                        <Link
                            href="/"
                            className="login-brand login-mobile-brand"
                            aria-label="Lab Scheduling, kembali ke beranda"
                        >
                            <span className="login-brand-mark">
                                <FlaskConical size={28} strokeWidth={1.7} />
                            </span>

                            <span className="login-brand-university">
                                <strong>UNIVERSITAS BHAYANGKARA</strong>
                                <small>JAKARTA RAYA</small>
                            </span>

                            <span className="login-brand-divider" />

                            <span className="login-brand-product">
                                <b>Lab</b> Scheduling
                            </span>
                        </Link>

                        {/* Tombol kembali versi desktop (di luar kartu) */}
                        <Link
                            href="/"
                            className="login-back-link login-back-link-desktop"
                        >
                            <ArrowLeft size={17} />
                            <span>Kembali ke Beranda</span>
                        </Link>

                        <div className="login-card">
                            {/* Tombol kembali versi mobile (di dalam kartu) */}
                            <Link
                                href="/"
                                className="login-back-link login-back-link-mobile"
                            >
                                <ArrowLeft size={19} />
                                <span>Kembali ke Beranda</span>
                            </Link>

                            <div className="login-card-heading">
                                <span className="login-card-kicker">
                                    LUPA KATA SANDI
                                </span>

                                <h2>
                                    Atur Ulang <span>Kata Sandi Anda</span>
                                </h2>

                                <p>
                                    Masukkan alamat email yang terdaftar pada
                                    akun Anda. Kami akan mengirimkan tautan
                                    untuk mengatur ulang kata sandi.
                                </p>
                            </div>

                            {status && (
                                <div className="login-status" role="status">
                                    {status}
                                </div>
                            )}

                            <Form {...email.form()} className="login-form">
                                {({ processing, errors }) => (
                                    <>
                                        <div className="login-field">
                                            <label htmlFor="email">
                                                Alamat Email
                                            </label>

                                            <div className="login-input-wrap">
                                                <Mail size={20} />

                                                <input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required
                                                    autoComplete="off"
                                                    placeholder="contoh@ubharajaya.ac.id"
                                                />
                                            </div>

                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <button
                                            type="submit"
                                            className="login-submit"
                                            disabled={processing}
                                            data-test="email-password-reset-link-button"
                                        >
                                            {processing
                                                ? 'Memproses...'
                                                : 'Kirim Link Reset'}
                                            <ArrowRight size={20} />
                                        </button>
                                    </>
                                )}
                            </Form>

                            <div className="fp-separator" role="separator">
                                <span className="fp-separator-line" />
                                <span className="fp-separator-text">atau</span>
                                <span className="fp-separator-line" />
                            </div>

                            <Link href={login()} className="fp-login-link">
                                <KeyRound size={20} />
                                <span>Kembali ke Halaman Masuk</span>
                            </Link>

                            <div className="fp-info-note">
                                <Info size={20} />
                                <p>
                                    Link reset kata sandi akan dikirim dalam
                                    beberapa menit. Jika tidak menerima email,
                                    periksa folder spam Anda.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}

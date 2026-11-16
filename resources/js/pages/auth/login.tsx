import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    Database,
    Eye,
    EyeOff,
    FlaskConical,
    LockKeyhole,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

import './login.css';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const [remember, setRemember] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    return (
        <>
            <Head title="Masuk | Lab Scheduling" />

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
                            SISTEM INFORMASI LABORATORIUM
                        </span>

                        <h1>
                            Selamat Datang Kembali
                            <span>Lab Scheduling</span>
                        </h1>

                        <p className="login-showcase-description">
                            Masuk untuk mengelola jadwal praktikum, booking
                            laboratorium, dan aktivitas laboratorium Anda.
                        </p>

                        <div className="login-benefits">
                            <div className="login-benefit">
                                <span className="login-benefit-icon">
                                    <CalendarDays size={27} />
                                </span>
                                <div>
                                    <strong>
                                        Jadwal &amp; booking terpusat
                                    </strong>
                                    <p>
                                        Kelola semua permintaan lab dalam satu
                                        sistem.
                                    </p>
                                </div>
                            </div>

                            <div className="login-benefit">
                                <span className="login-benefit-icon">
                                    <ShieldCheck size={29} />
                                </span>
                                <div>
                                    <strong>Persetujuan lebih terarah</strong>
                                    <p>
                                        Alur persetujuan sesuai role dan
                                        kebutuhan.
                                    </p>
                                </div>
                            </div>

                            <div className="login-benefit">
                                <span className="login-benefit-icon">
                                    <Database size={28} />
                                </span>
                                <div>
                                    <strong>Data laboratorium terkelola</strong>
                                    <p>
                                        Akses data lab, pengguna, dan laporan
                                        dengan mudah.
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
                <section className="login-panel" aria-label="Form masuk">
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
                                    AKSES PENGGUNA TERDAFTAR
                                </span>

                                <h2>
                                    Masuk ke <span>Akun Anda</span>
                                </h2>

                                <p>
                                    Gunakan akun yang diberikan oleh Ketua Lab.
                                </p>
                            </div>

                            {status && (
                                <div className="login-status" role="status">
                                    {status}
                                </div>
                            )}

                            <Form
                                {...store.form()}
                                resetOnSuccess={['password']}
                                className="login-form"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="login-field">
                                            <label htmlFor="email">Email</label>

                                            <div className="login-input-wrap">
                                                <UserRound size={20} />

                                                <input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required
                                                    autoComplete="username"
                                                    placeholder="Masukkan email akun"
                                                />
                                            </div>

                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="login-field">
                                            <div className="login-label-row">
                                                <label htmlFor="password">
                                                    Password
                                                </label>

                                                {canResetPassword && (
                                                    <Link
                                                        href={request()}
                                                        className="login-forgot-link"
                                                    >
                                                        Lupa password?
                                                    </Link>
                                                )}
                                            </div>

                                            <div className="login-input-wrap login-password-wrap">
                                                <LockKeyhole size={20} />

                                                <input
                                                    id="password"
                                                    type={
                                                        showPassword
                                                            ? 'text'
                                                            : 'password'
                                                    }
                                                    name="password"
                                                    required
                                                    autoComplete="current-password"
                                                    placeholder="Masukkan password"
                                                />

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setShowPassword(
                                                            (isVisible) =>
                                                                !isVisible,
                                                        )
                                                    }
                                                    aria-label={
                                                        showPassword
                                                            ? 'Sembunyikan password'
                                                            : 'Tampilkan password'
                                                    }
                                                >
                                                    {showPassword ? (
                                                        <EyeOff size={18} />
                                                    ) : (
                                                        <Eye size={18} />
                                                    )}
                                                </button>
                                            </div>

                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <div className="login-options">
                                            <label className="login-remember">
                                                <input
                                                    type="checkbox"
                                                    name="remember"
                                                    checked={remember}
                                                    onChange={(event) =>
                                                        setRemember(
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                />
                                                <span className="login-checkmark" />
                                                Ingat saya
                                            </label>
                                        </div>

                                        <button
                                            type="submit"
                                            className="login-submit"
                                            disabled={processing}
                                            data-test="login-button"
                                        >
                                            {processing
                                                ? 'Memproses...'
                                                : 'Masuk'}
                                            <ArrowRight size={20} />
                                        </button>
                                    </>
                                )}
                            </Form>

                            {/* Passkey berada di dalam kartu login */}
                            <div className="login-passkey">
                                <PasskeyVerify
                                    label="Masuk dengan Passkey"
                                    loadingLabel="Memverifikasi..."
                                    separator="atau"
                                />
                            </div>

                            <a
                                className="login-help-link"
                                href="mailto:lab@ubharajaya.ac.id"
                            >
                                <ShieldCheck size={21} />
                                <span>Bantuan akses akun</span>
                            </a>

                            <p className="login-contact-note">
                                Belum memiliki akun?{' '}
                                <strong>Hubungi Ketua Lab.</strong>
                            </p>

                            <div className="login-security-note">
                                <ShieldCheck size={16} />
                                <span>Koneksi aman</span>
                                <i />
                                <span>Akses khusus pengguna terdaftar</span>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}

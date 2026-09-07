import { Form, Head, Link } from '@inertiajs/react';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

/**
 * Acceso al panel. Es parte del sitio público —lleva su cabecera y su pie— así
 * que se arma con el sistema de diseño de `alfa.css`, sin shadcn ni Tailwind.
 */
export default function Login({ status, canResetPassword }: Props) {
    return (
        <section className="shell auth">
            <Head title="Ingresar">
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <p className="eyebrow">Uso interno</p>
            <h1>Panel Administrativo</h1>
            <p className="lede">Acceso para el equipo de Alfa Automotores.</p>

            {status && <p className="auth__aviso">{status}</p>}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="auth__form"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="auth__field">
                            <label className="field-label" htmlFor="email">
                                Correo
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                className="auth__input"
                                required
                                autoFocus
                                autoComplete="username"
                            />
                            {errors.email && (
                                <p className="auth__error">{errors.email}</p>
                            )}
                        </div>

                        <div className="auth__field">
                            <div className="auth__label-row">
                                <label
                                    className="field-label"
                                    htmlFor="password"
                                >
                                    Contraseña
                                </label>
                                {canResetPassword && (
                                    <Link
                                        href={request()}
                                        className="auth__link"
                                    >
                                        ¿Olvidaste tu contraseña?
                                    </Link>
                                )}
                            </div>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                className="auth__input"
                                required
                                autoComplete="current-password"
                            />
                            {errors.password && (
                                <p className="auth__error">{errors.password}</p>
                            )}
                        </div>

                        <label className="auth__remember">
                            <input
                                id="remember"
                                name="remember"
                                type="checkbox"
                            />
                            <span className="field-label">Recordarme</span>
                        </label>

                        <button
                            type="submit"
                            className="btn"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing ? 'Ingresando…' : 'Ingresar'}
                        </button>
                    </>
                )}
            </Form>
        </section>
    );
}

import { Form, Head, Link } from '@inertiajs/react';
import { login } from '@/routes';
import { email } from '@/routes/password';

/**
 * Pedido del enlace de recuperación. Comparte el chrome público con `login`.
 */
export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <section className="shell auth">
            <Head title="Recuperar contraseña">
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <p className="eyebrow">Uso interno</p>
            <h1>Recuperar contraseña</h1>
            <p className="lede">
                Dejanos tu correo y te mandamos un enlace para elegir una nueva.
            </p>

            {status && <p className="auth__aviso">{status}</p>}

            <Form {...email.form()} className="auth__form">
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

                        <button
                            type="submit"
                            className="btn"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing ? 'Enviando…' : 'Enviar enlace'}
                        </button>
                    </>
                )}
            </Form>

            <p className="auth__volver">
                <Link href={login()}>Volver a ingresar</Link>
            </p>
        </section>
    );
}

import { Form, Head, Link } from '@inertiajs/react';
import { login } from '@/routes';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

/**
 * Alta de la contraseña nueva. El correo llega fijado desde el enlace del mail,
 * así que va de sólo lectura y viaja por `transform` junto al token.
 */
export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <section className="shell auth">
            <Head title="Nueva contraseña">
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <p className="eyebrow">Uso interno</p>
            <h1>Nueva contraseña</h1>
            <p className="lede">Elegí la contraseña con la que vas a entrar.</p>

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
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
                                value={email}
                                autoComplete="username"
                                readOnly
                            />
                            {errors.email && (
                                <p className="auth__error">{errors.email}</p>
                            )}
                        </div>

                        <div className="auth__field">
                            <label className="field-label" htmlFor="password">
                                Contraseña
                            </label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                className="auth__input"
                                required
                                autoFocus
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            {errors.password && (
                                <p className="auth__error">{errors.password}</p>
                            )}
                        </div>

                        <div className="auth__field">
                            <label
                                className="field-label"
                                htmlFor="password_confirmation"
                            >
                                Repetir contraseña
                            </label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                className="auth__input"
                                required
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            {errors.password_confirmation && (
                                <p className="auth__error">
                                    {errors.password_confirmation}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            className="btn"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing ? 'Guardando…' : 'Guardar contraseña'}
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

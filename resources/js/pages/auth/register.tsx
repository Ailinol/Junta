import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
export default function Register() {
    const [generalError, setGeneralError] = useState('');
    return (
        <>
            <Head title="Criar conta" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="space-y-4"
                noValidate
                onStart={() => setGeneralError('')}
                onNetworkError={() => {
                    setGeneralError(
                        'Não foi possível comunicar com o servidor. Verifique a ligação.',
                    );
                    return false;
                }}
                onHttpException={() => {
                    setGeneralError(
                        'O servidor não concluiu a operação. Tente novamente.',
                    );
                    return false;
                }}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nome</Label>
                            <Input
                                id="name"
                                name="name"
                                autoComplete="name"
                                autoFocus
                                required
                                aria-invalid={!!errors.name}
                                aria-describedby={
                                    errors.name ? 'name-error' : undefined
                                }
                            />
                            <InputError
                                id="name-error"
                                role="alert"
                                message={errors.name}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                required
                                aria-invalid={!!errors.email}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                            />
                            <InputError
                                id="email-error"
                                role="alert"
                                message={errors.email}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Palavra-passe</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                required
                                aria-invalid={!!errors.password}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmar palavra-passe
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                        <p className="text-xs leading-5 text-muted-foreground">
                            Criar uma conta não atribui permissões de
                            organizador ou fornecedor.
                        </p>
                        <Button
                            className="w-full"
                            type="submit"
                            disabled={processing}
                            data-test="register-user-button"
                        >
                            {processing && <Spinner />}Criar conta
                        </Button>
                        {generalError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {generalError}
                            </p>
                        )}
                    </>
                )}
            </Form>
            <p className="mt-6 text-center text-sm text-muted-foreground">
                Já tem conta?{' '}
                <Link
                    href={login()}
                    className="font-semibold text-primary hover:underline"
                >
                    Iniciar sessão
                </Link>
            </p>
        </>
    );
}
Register.layout = {
    title: 'Crie a sua conta',
    description: 'Comece a acompanhar oportunidades de compra colectiva.',
};

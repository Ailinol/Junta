import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head, Link } from '@inertiajs/react';
export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    return (
        <>
            <Head title="Iniciar sessão" />
            {status && (
                <p
                    role="status"
                    className="mb-5 rounded-lg bg-muted p-3 text-sm text-primary"
                >
                    {status}
                </p>
            )}
            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="space-y-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="username"
                                required
                                autoFocus
                                aria-invalid={!!errors.email}
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="password">Palavra-passe</Label>
                                {canResetPassword && (
                                    <Link
                                        href={request()}
                                        className="text-xs font-medium text-primary hover:underline"
                                    >
                                        Esqueceu-se?
                                    </Link>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="current-password"
                                required
                                aria-invalid={!!errors.password}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <label className="flex items-center gap-2 text-sm text-muted-foreground">
                            <input
                                name="remember"
                                type="checkbox"
                                className="size-4 accent-primary"
                            />
                            Manter sessão iniciada
                        </label>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full"
                            data-test="login-button"
                        >
                            {processing && <Spinner />}Iniciar sessão
                        </Button>
                    </>
                )}
            </Form>
            <p className="mt-6 text-center text-sm text-muted-foreground">
                Ainda não tem conta?{' '}
                <Link
                    href={register()}
                    className="font-semibold text-primary hover:underline"
                >
                    Criar conta
                </Link>
            </p>
        </>
    );
}
Login.layout = {
    title: 'Bem-vindo de volta',
    description: 'Entre para acompanhar as suas compras.',
};

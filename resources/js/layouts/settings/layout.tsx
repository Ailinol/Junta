import { useCurrentUrl } from '@/hooks/use-current-url';
import { edit as appearance } from '@/routes/appearance';
import { perfil } from '@/routes/consumidor';
import { edit as profile } from '@/routes/profile';
import { edit as security } from '@/routes/security';
import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
const items = [
    { title: 'Dados pessoais', href: profile() },
    { title: 'Contactos e alertas', href: perfil() },
    { title: 'Segurança', href: security() },
    { title: 'Aparência', href: appearance() },
];
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl } = useCurrentUrl();
    return (
        <main className="junta-settings mx-auto w-full max-w-5xl px-4 py-6 pb-24 break-words sm:px-6 lg:px-8">
            <header className="mb-7 pt-2">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Definições da conta
                </h1>
            </header>
            <nav
                aria-label="Definições"
                className="mb-6 flex gap-2 overflow-x-auto border-b border-border pb-3"
            >
                {items.map((item) => (
                    <Link
                        key={item.title}
                        href={item.href}
                        aria-current={
                            isCurrentUrl(item.href) ? 'page' : undefined
                        }
                        className={
                            'shrink-0 rounded-lg border-b-2 px-3 py-3 text-sm font-medium ' +
                            (isCurrentUrl(item.href)
                                ? 'border-primary bg-junta-primary-soft text-primary'
                                : 'border-transparent text-muted-foreground hover:bg-junta-primary-soft hover:text-foreground')
                        }
                    >
                        {item.title}
                    </Link>
                ))}
            </nav>
            <div className="space-y-6">{children}</div>
        </main>
    );
}

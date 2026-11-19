import {
    ArrowUpRight,
    BriefcaseBusiness,
    Layers,
    ShieldCheck,
    ShoppingBag,
    Settings,
} from 'lucide-react';
import { Page } from '@/components/junta/Page';
import { dashboard as consumer } from '@/routes/consumidor';
import { dashboard as organizer } from '@/routes/organizador';
import { dashboard as supplier } from '@/routes/fornecedor';
import { dashboard as administrator } from '@/routes/admin';
import { edit } from '@/routes/profile';
import { Link, usePage } from '@inertiajs/react';
export default function Dashboard() {
    const { auth } = usePage().props;
    const areas = [
        {
            title: 'Compras e participações',
            icon: ShoppingBag,
            label: 'Consumidor',
            description:
                'Explore campanhas, indique quantidades e acompanhe os seus pagamentos e recolhas.',
            href: consumer(),
            allowed: true,
        },
        {
            title: 'Gestão de campanhas',
            icon: Layers,
            label: 'Organizador',
            description:
                'Prepare campanhas, reúna procura, compare propostas e acompanhe a execução da compra.',
            href: organizer(),
            allowed: !!auth.permissions?.organizer,
        },
        {
            title: 'Fornecimento',
            icon: BriefcaseBusiness,
            label: 'Fornecedor',
            description:
                'Consulte oportunidades, apresente propostas e confirme as condições das compras seleccionadas.',
            href: supplier(),
            allowed: !!auth.permissions?.supplier,
        },
        {
            title: 'Administração',
            icon: ShieldCheck,
            label: 'Administrador',
            description:
                'Acompanhe candidaturas, utilizadores, fornecedores, compras e ocorrências.',
            href: administrator(),
            allowed: !!auth.permissions?.administrator,
        },
    ].filter((area) => area.allowed);
    return (
        <Page
            title="O seu espaço no Junta"
            description="As suas compras, campanhas e oportunidades começam aqui. Escolha a área que pretende abrir."
        >
            <div className="grid gap-5 md:grid-cols-2">
                {areas.map((area) => (
                    <Link
                        key={area.title}
                        href={area.href}
                        className="group flex flex-col rounded-2xl border border-border bg-card p-6 transition-colors hover:border-primary/35 sm:p-8"
                    >
                        <div className="mb-8 flex items-center justify-between">
                            <span className="flex size-12 items-center justify-center rounded-2xl bg-junta-primary-soft text-primary">
                                <area.icon className="size-6" />
                            </span>
                            <span className="rounded-full border border-border px-3 py-1 text-xs text-muted-foreground">
                                {area.label}
                            </span>
                        </div>
                        <h2 className="text-xl font-semibold tracking-tight">
                            {area.title}
                        </h2>
                        <p className="mt-3 max-w-lg text-sm leading-7 text-muted-foreground">
                            {area.description}
                        </p>
                        <span className="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-primary">
                            Abrir área{' '}
                            <ArrowUpRight className="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none" />
                        </span>
                    </Link>
                ))}
            </div>
            <Link
                href={edit()}
                className="mt-6 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
            >
                <Settings className="size-4" />
                Gerir dados pessoais e definições da conta
            </Link>
        </Page>
    );
}

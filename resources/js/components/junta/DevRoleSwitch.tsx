import { router, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Layers,
    ShieldCheck,
    ShoppingBag,
    Users,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard as admin } from '@/routes/admin';
import { dashboard as consumidor } from '@/routes/consumidor';
import { dashboard as fornecedor } from '@/routes/fornecedor';
import { dashboard as organizador } from '@/routes/organizador';
export function DevRoleSwitch() {
    const { auth } = usePage().props;
    if (!auth?.user) return null;
    const profiles = [
        {
            label: 'Consumidor',
            route: consumidor,
            icon: ShoppingBag,
            allowed: true,
        },
        {
            label: 'Organizador',
            route: organizador,
            icon: Layers,
            allowed: !!auth.permissions?.organizer,
        },
        {
            label: 'Fornecedor',
            route: fornecedor,
            icon: BriefcaseBusiness,
            allowed: !!auth.permissions?.supplier,
        },
        {
            label: 'Administrador',
            route: admin,
            icon: ShieldCheck,
            allowed: !!auth.permissions?.administrator,
        },
    ].filter((p) => p.allowed);
    return (
        <div className="shrink-0">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label="Escolher área da conta"
                        className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
                    >
                        <Users className="size-4" aria-hidden="true" />
                        <span className="hidden sm:inline">Áreas</span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    side="bottom"
                    sideOffset={10}
                    className="w-60 p-2"
                >
                    <DropdownMenuLabel>
                        Áreas da sua conta
                        <p className="mt-1 text-xs font-normal text-muted-foreground">
                            Escolha onde pretende trabalhar
                        </p>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {profiles.map((profile) => (
                        <DropdownMenuItem
                            key={profile.label}
                            onSelect={() => router.visit(profile.route())}
                            className="min-h-11 gap-3 px-3"
                        >
                            <profile.icon
                                className="size-4"
                                aria-hidden="true"
                            />
                            {profile.label}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubTrigger,
    DropdownMenuSubContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { perfil, candidatura } from '@/routes/consumidor';
import { create as supplierRegistration } from '@/routes/fornecedor/adesao';
import { dashboard as organizerDashboard } from '@/routes/organizador';
import { dashboard as supplierDashboard } from '@/routes/fornecedor';
import { dashboard as adminDashboard } from '@/routes/admin';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';
import { dashboard as consumerHome } from '@/routes/consumidor';
import { home } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User;
    consumer?: boolean;
    administrator?: boolean;
};

export function UserMenuContent({
    user,
    consumer = false,
    administrator = false,
}: Props) {
    const { auth } = usePage().props;
    const cleanup = useMobileNavigation();
    const { appearance, updateAppearance } = useAppearance();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={consumer ? perfil() : edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Definições
                    </Link>
                </DropdownMenuItem>
                {(consumer || administrator) && auth.permissions?.organizer && (
                    <DropdownMenuItem asChild>
                        <Link href={organizerDashboard()} onClick={cleanup}>
                            Área do organizador
                        </Link>
                    </DropdownMenuItem>
                )}
                {(consumer || administrator) && auth.permissions?.supplier && (
                    <DropdownMenuItem asChild>
                        <Link href={supplierDashboard()} onClick={cleanup}>
                            Área do fornecedor
                        </Link>
                    </DropdownMenuItem>
                )}
                {consumer && auth.permissions?.administrator && (
                    <DropdownMenuItem asChild>
                        <Link href={adminDashboard()} onClick={cleanup}>
                            Administração
                        </Link>
                    </DropdownMenuItem>
                )}
                {consumer && !auth.permissions?.organizer && (
                    <DropdownMenuItem asChild>
                        <Link href={candidatura()} onClick={cleanup}>
                            Ser organizador
                        </Link>
                    </DropdownMenuItem>
                )}
                {consumer && !auth.permissions?.supplier && (
                    <DropdownMenuItem asChild>
                        <Link href={supplierRegistration()} onClick={cleanup}>
                            Ser fornecedor
                        </Link>
                    </DropdownMenuItem>
                )}
                {administrator && (
                    <>
                        <DropdownMenuItem asChild>
                            <Link href={consumerHome()}>
                                Área do consumidor
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={home()}>Ver site</Link>
                        </DropdownMenuItem>
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                Aparência
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                <DropdownMenuRadioGroup
                                    value={appearance}
                                    onValueChange={(value) =>
                                        updateAppearance(value as Appearance)
                                    }
                                >
                                    {[
                                        ['light', 'Claro'],
                                        ['dark', 'Escuro'],
                                        ['system', 'Sistema'],
                                    ].map(([value, label]) => (
                                        <DropdownMenuRadioItem
                                            key={value}
                                            value={value}
                                        >
                                            {label}
                                        </DropdownMenuRadioItem>
                                    ))}
                                </DropdownMenuRadioGroup>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                    </>
                )}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Terminar sessão
                </Link>
            </DropdownMenuItem>
        </>
    );
}

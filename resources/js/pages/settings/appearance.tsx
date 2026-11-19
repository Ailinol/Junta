import { Head } from '@inertiajs/react';
import AppearanceToggleTab from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit } from '@/routes/appearance';
export default function Appearance() {
    return (
        <>
            <Head title="Aparência" />
            <section className="junta-surface-card max-w-3xl space-y-5">
                <Heading
                    variant="small"
                    title="Aparência"
                    description="Escolha o tema claro, escuro ou acompanhe a preferência do seu dispositivo."
                />
                <AppearanceToggleTab />
                <p className="text-sm text-muted-foreground">
                    A preferência fica guardada neste navegador e aplica-se a
                    todas as áreas do Junta.
                </p>
            </section>
        </>
    );
}
Appearance.layout = { breadcrumbs: [{ title: 'Aparência', href: edit() }] };

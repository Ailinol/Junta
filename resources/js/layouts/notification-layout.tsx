import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AdminLayout from '@/layouts/admin/layout';
import AppLayout from '@/layouts/app-layout';
export default function NotificationLayout({
    children,
}: {
    children: ReactNode;
}) {
    const page = usePage();
    return page.url.includes('area=admin') &&
        page.props.auth.permissions?.administrator ? (
        <AdminLayout>{children}</AdminLayout>
    ) : (
        <AppLayout>{children}</AppLayout>
    );
}

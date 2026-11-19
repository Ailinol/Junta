import { router } from '@inertiajs/react';
type MutationRoute = {
    url: string;
    method: 'post' | 'patch' | 'put' | 'delete';
};
type Payload = Record<string, string | number | boolean | null | string[]>;
export class ValidationFailure extends Error {
    constructor(public readonly errors: Record<string, string>) {
        super(Object.values(errors).join(' '));
        this.name = 'ValidationFailure';
    }
}
export function mutate(
    route: MutationRoute,
    data: Payload = {},
): Promise<void> {
    return new Promise((resolve, reject) => {
        router.visit(route.url, {
            method: route.method,
            data,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => resolve(),
            onError: (errors) => reject(new ValidationFailure(errors)),
            onCancel: () => reject(new Error('Operação cancelada.')),
            onHttpException: () => {
                reject(
                    new Error(
                        'O servidor não concluiu a operação. Tente novamente.',
                    ),
                );
                return false;
            },
            onNetworkError: () => {
                reject(
                    new Error(
                        'Não foi possível comunicar com o servidor. Verifique a ligação.',
                    ),
                );
                return false;
            },
        });
    });
}
export function errorMessage(error: unknown): string {
    return error instanceof Error
        ? error.message
        : 'Não foi possível concluir a operação.';
}

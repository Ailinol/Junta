import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    compact = false,
    ...props
}: HTMLAttributes<HTMLDivElement> & { compact?: boolean }) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Claro' },
        { value: 'dark', icon: Moon, label: 'Escuro' },
        { value: 'system', icon: Monitor, label: 'Sistema' },
    ];

    return (
        <div
            role="group"
            aria-label="Tema da aplicação"
            className={cn(
                'inline-flex max-w-full shrink-0 gap-1 rounded-lg border border-border bg-muted p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    type="button"
                    key={value}
                    aria-label={'Tema ' + label.toLowerCase()}
                    aria-pressed={appearance === value}
                    title={label}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex min-h-9 items-center justify-center rounded-md transition-colors',
                        compact
                            ? 'w-9 p-2'
                            : 'flex-col gap-1 px-3 py-2 sm:flex-row sm:gap-0',
                        appearance === value
                            ? 'bg-card text-primary shadow-xs ring-1 ring-border'
                            : 'text-muted-foreground hover:bg-card hover:text-foreground',
                    )}
                >
                    <Icon className="size-4" aria-hidden="true" />
                    <span className={compact ? 'sr-only' : 'text-sm sm:ml-1.5'}>
                        {label}
                    </span>
                </button>
            ))}
        </div>
    );
}

import { Link } from '@inertiajs/react';

type BrandLogoProps = {
    href?: string;
    compact?: boolean;
    subtitle?: string;
    className?: string;
    inverse?: boolean;
};

export function BrandLogo({
    href = '/',
    compact = false,
    subtitle,
    className = '',
    inverse = false,
}: BrandLogoProps) {
    return (
        <Link
            href={href}
            aria-label="Junta — Juntos compramos melhor"
            className={`inline-flex min-w-0 items-center gap-2.5 ${className}`}
        >
            <img
                src="/junta-mark.svg"
                alt=""
                aria-hidden="true"
                className={compact ? 'size-9 shrink-0' : 'size-12 shrink-0'}
            />
            <span className="grid min-w-0 text-left leading-none">
                <span
                    className={`truncate font-extrabold lowercase ${inverse ? 'text-white' : 'text-primary'} ${compact ? 'text-lg' : 'text-2xl'}`}
                >
                    junta
                </span>
                {(subtitle || !compact) && (
                    <span
                        className={`mt-1 truncate text-xs font-medium ${inverse ? 'text-[#C5D9CE]' : 'text-muted-foreground'}`}
                    >
                        {subtitle ?? 'Juntos compramos melhor'}
                    </span>
                )}
            </span>
        </Link>
    );
}

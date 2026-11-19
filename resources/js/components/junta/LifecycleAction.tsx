import { useState, type FormEvent } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { mutate, errorMessage } from '@/lib/server-mutation';
import type { RouteDefinition } from '@/wayfinder';

export function LifecycleAction({
    action,
    label,
    description,
}: {
    action: RouteDefinition<'post'>;
    label: string;
    description: string;
}) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const submit = async (event: FormEvent) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        setError('');
        try {
            await mutate(action, { reason });
            setOpen(false);
            setReason('');
        } catch (failure) {
            setError(errorMessage(failure));
        } finally {
            setSaving(false);
        }
    };
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="junta-btn junta-btn-sm junta-btn-outline"
                >
                    {label}
                </button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{label}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => void submit(event)}
                    className="space-y-4"
                >
                    <label className="grid gap-2 text-sm font-medium">
                        Motivo
                        <textarea
                            required
                            minLength={1}
                            maxLength={1000}
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            className="junta-input min-h-24"
                        />
                    </label>
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <button
                            type="button"
                            disabled={saving}
                            onClick={() => setOpen(false)}
                            className="junta-btn junta-btn-md junta-btn-outline"
                        >
                            Voltar
                        </button>
                        <button
                            disabled={saving || !reason.trim()}
                            className="junta-btn junta-btn-md junta-btn-primary"
                        >
                            {saving ? 'A guardar…' : 'Confirmar'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

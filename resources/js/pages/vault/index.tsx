import { Form, Head, Link, router } from '@inertiajs/react';
import { FolderOpen, Notebook, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { requestJson } from '@/lib/kite-http';
import { store as pickFolder } from '@/routes/vault-folder';
import { destroy, show, store } from '@/routes/vaults';
import type { VaultSummary } from '@/types';

type Props = {
    vaults: (VaultSummary & {
        notes_count: number;
        opened_at: string | null;
    })[];
    canPickFolder: boolean;
};

export default function VaultIndex({ vaults, canPickFolder }: Props) {
    const [picking, setPicking] = useState(false);

    const openWithPicker = async () => {
        setPicking(true);

        try {
            const { path } = await requestJson<{ path: string | null }>(
                pickFolder(),
            );

            if (path) {
                router.post(store().url, { path });
            }
        } finally {
            setPicking(false);
        }
    };

    return (
        <>
            <Head title="Open a vault" />

            <main className="kite-drag relative flex min-h-svh items-center justify-center overflow-hidden bg-background p-6">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-40 left-1/2 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,color-mix(in_oklch,var(--primary)_22%,transparent),transparent)] blur-2xl"
                />
                <div
                    aria-hidden
                    className="kite-dot-grid pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,black_10%,transparent_70%)] opacity-60"
                />
                <div className="kite-no-drag relative w-full max-w-xl space-y-10">
                    <div className="flex flex-col items-center gap-4 text-center">
                        <div className="flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-[oklch(0.62_0.2_310)] text-primary-foreground shadow-kite-lg ring-1 ring-white/20">
                            <AppLogoIcon className="size-9 fill-current" />
                        </div>
                        <h1 className="text-4xl font-bold tracking-tight">
                            Welcome to Kite
                        </h1>
                        <p className="max-w-sm text-[15px] leading-relaxed text-muted-foreground">
                            Kite works directly on a folder of markdown files on
                            your computer. Nothing leaves your machine.
                        </p>
                    </div>

                    <div className="space-y-4 rounded-2xl border bg-card/80 p-6 shadow-kite-lg backdrop-blur">
                        {canPickFolder && (
                            <Button
                                className="w-full"
                                size="lg"
                                onClick={() => void openWithPicker()}
                                disabled={picking}
                            >
                                {picking ? <Spinner /> : <FolderOpen />}
                                Choose a folder…
                            </Button>
                        )}

                        <Form {...store.form()} className="grid gap-2">
                            {({ processing, errors }) => (
                                <>
                                    <Label htmlFor="path">
                                        {canPickFolder
                                            ? 'Or enter a folder path'
                                            : 'Folder path'}
                                    </Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="path"
                                            name="path"
                                            placeholder="/Users/you/Notes"
                                            autoFocus={!canPickFolder}
                                            required
                                            className="font-mono text-sm"
                                        />
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Open
                                        </Button>
                                    </div>
                                    <InputError message={errors.path} />
                                </>
                            )}
                        </Form>
                    </div>

                    {vaults.length > 0 && (
                        <section className="space-y-2">
                            <h2 className="px-1 text-[11px] font-semibold tracking-wider text-muted-foreground/80 uppercase">
                                Recent vaults
                            </h2>
                            <ul className="divide-y overflow-hidden rounded-2xl border bg-card/80 shadow-kite-sm backdrop-blur">
                                {vaults.map((vault) => (
                                    <li
                                        key={vault.id}
                                        className="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-accent/60"
                                    >
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kite-accent-soft text-sm font-semibold text-primary">
                                            {vault.name
                                                .trim()
                                                .charAt(0)
                                                .toUpperCase() || (
                                                <Notebook className="size-4" />
                                            )}
                                        </span>
                                        <Link
                                            href={show(vault)}
                                            className="min-w-0 flex-1"
                                        >
                                            <div className="truncate font-medium">
                                                {vault.name}
                                            </div>
                                            <div className="truncate font-mono text-xs text-muted-foreground">
                                                {vault.path}
                                            </div>
                                        </Link>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {vault.notes_count} notes
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                                            aria-label={`Forget ${vault.name}`}
                                            onClick={() =>
                                                router.delete(
                                                    destroy(vault).url,
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            </main>
        </>
    );
}

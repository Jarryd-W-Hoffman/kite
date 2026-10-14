import { Form, Head, usePage } from '@inertiajs/react';
import { CalendarDays, Zap } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatShortcut } from '@/lib/shortcuts';
import { store } from '@/routes/capture';

type Props = {
    vault: { id: number; name: string } | null;
};

export default function CaptureCreate({ vault }: Props) {
    const { kite } = usePage().props;

    return (
        <>
            <Head title="Quick capture" />

            <main className="kite-drag flex h-svh flex-col bg-background">
                <div className="flex h-11 shrink-0 items-center gap-2 border-b border-border/70 px-4 text-[13px]">
                    <span className="flex size-5 items-center justify-center rounded-md bg-gradient-to-br from-primary to-[oklch(0.62_0.2_310)] text-primary-foreground">
                        <Zap className="size-3" />
                    </span>
                    <span className="font-semibold">Quick capture</span>
                    {vault && (
                        <span className="truncate text-muted-foreground">
                            · {vault.name}
                        </span>
                    )}
                </div>
                <Form
                    {...store.form()}
                    resetOnSuccess={['text']}
                    className="kite-no-drag flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <textarea
                                name="text"
                                autoFocus
                                required
                                aria-label="Capture"
                                data-test="capture-text"
                                placeholder={
                                    vault
                                        ? `Capture a thought for today in ${vault.name}…`
                                        : 'Open a vault in Kite first.'
                                }
                                onKeyDown={(event) => {
                                    if (
                                        (event.metaKey || event.ctrlKey) &&
                                        event.key === 'Enter'
                                    ) {
                                        event.preventDefault();
                                        event.currentTarget.form?.requestSubmit();
                                    }

                                    if (event.key === 'Escape') {
                                        window.close();
                                    }
                                }}
                                className="min-h-0 flex-1 resize-none bg-transparent px-5 py-4 text-[16px] leading-relaxed outline-none placeholder:text-muted-foreground/60"
                            />
                            <InputError
                                message={errors.text}
                                className="px-5"
                            />
                            <div className="flex items-center gap-2 border-t border-border/70 bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground">
                                <CalendarDays className="size-3.5" />
                                Adds a timestamped line to today&apos;s note
                                <Button
                                    type="submit"
                                    size="sm"
                                    className="ml-auto"
                                    disabled={processing || !vault}
                                >
                                    {processing && <Spinner />}
                                    Capture
                                    <kbd className="text-[10px] opacity-70">
                                        {formatShortcut(
                                            'Mod+Enter',
                                            kite.platform,
                                        )}
                                    </kbd>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}

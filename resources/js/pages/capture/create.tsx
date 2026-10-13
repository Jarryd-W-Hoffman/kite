import { Form, Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/capture';

type Props = {
    vault: { id: number; name: string } | null;
};

export default function CaptureCreate({ vault }: Props) {
    return (
        <>
            <Head title="Quick capture" />

            <main className="kite-drag flex h-svh flex-col bg-background p-4">
                <Form
                    {...store.form()}
                    resetOnSuccess={['text']}
                    className="kite-no-drag flex flex-1 flex-col gap-3"
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
                                className="min-h-0 flex-1 resize-none rounded-lg border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                            />
                            <InputError message={errors.text} />
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
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
                                        ⌘↵
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

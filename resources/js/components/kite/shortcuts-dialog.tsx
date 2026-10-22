import { router, usePage } from '@inertiajs/react';
import { RotateCcw, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Kbd } from '@/components/kite/kbd';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AppCommand } from '@/hooks/use-app-commands';
import {
    acceleratorFor,
    RECORDED_COMMAND_EVENT,
    setRecordingShortcut,
    shortcutText,
} from '@/lib/key-bindings';
import { formatShortcut } from '@/lib/shortcuts';
import { cn } from '@/lib/utils';
import { update } from '@/routes/key-bindings';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * A shortcut and what it does: a menu command's, which can be changed, or a fixed one.
 */
type Entry = { label: string } & (
    | { command: AppCommand }
    | { shortcut: string }
);

const SECTIONS: { title: string; entries: Entry[] }[] = [
    {
        title: 'General',
        entries: [
            { command: 'search', label: 'Search notes' },
            { command: 'new-note', label: 'New note' },
            { command: 'new-from-template', label: 'New note from template' },
            { command: 'daily-note', label: "Today's note" },
            { command: 'graph', label: 'Graph view' },
            { command: 'open-vault', label: 'Open another vault' },
            { command: 'preferences', label: 'Preferences' },
            { command: 'toggle-theme', label: 'Toggle dark mode' },
            { command: 'shortcuts', label: 'Keyboard shortcuts' },
        ],
    },
    {
        title: 'Tabs and views',
        entries: [
            { shortcut: 'Ctrl+Tab', label: 'Next tab' },
            { shortcut: 'Ctrl+Shift+Tab', label: 'Previous tab' },
            { command: 'close-tab', label: 'Close tab' },
            { command: 'toggle-preview', label: 'Toggle preview' },
            { command: 'focus-mode', label: 'Focus mode (Esc to leave)' },
        ],
    },
    {
        title: 'Editor',
        entries: [
            { shortcut: 'Mod+S', label: 'Save now' },
            { shortcut: 'Mod+B', label: 'Bold' },
            { shortcut: 'Mod+I', label: 'Italic' },
            { shortcut: 'Mod+F', label: 'Find and replace' },
            { shortcut: 'Mod+Z', label: 'Undo' },
            { shortcut: 'Mod+Shift+Z', label: 'Redo' },
            { shortcut: 'Mod+Alt+M', label: 'Comment on the selection' },
            { shortcut: '[[', label: 'Link to a note' },
        ],
    },
    {
        title: 'Links and search',
        entries: [
            { shortcut: 'Mod+Click', label: 'Open a link in a new tab' },
            {
                shortcut: 'Mod+Shift+Click',
                label: 'Open a link beside this note',
            },
            {
                shortcut: 'Shift+Enter',
                label: 'Open a search result beside this note',
            },
            {
                shortcut: 'Mod+Enter',
                label: 'Create a note from the search text',
            },
        ],
    },
];

/**
 * The commands whose shortcuts can be changed, in the order the dialog lists them.
 */
const COMMANDS = SECTIONS.flatMap((section) =>
    section.entries.flatMap((entry) => ('command' in entry ? [entry] : [])),
);

/**
 * Every keyboard shortcut in one place, written for the current platform. Choose Customise to
 * give the menu commands other shortcuts, or none.
 */
export function ShortcutsDialog({ open, onOpenChange }: Props) {
    const { kite } = usePage().props;
    const [customising, setCustomising] = useState(false);
    const [recording, setRecording] = useState<AppCommand | null>(null);
    const [errors, setErrors] = useState<Partial<Record<AppCommand, string>>>(
        {},
    );
    const recordingRef = useRef(recording);

    const bindings = kite.key_bindings;
    const defaults = kite.default_key_bindings;
    const changed = COMMANDS.filter(
        ({ command }) => bindings[command] !== defaults[command],
    );

    const save = (changes: Partial<Record<AppCommand, string | null>>) => {
        setErrors((current) => {
            const next = { ...current };

            for (const command of Object.keys(changes)) {
                delete next[command as AppCommand];
            }

            return next;
        });

        router.put(
            update().url,
            { bindings: changes },
            {
                only: ['kite'],
                preserveState: true,
                preserveScroll: true,
                onError: (failed) =>
                    setErrors((current) => {
                        const next = { ...current };

                        for (const [key, message] of Object.entries(failed)) {
                            next[key.replace(/^bindings\./, '') as AppCommand] =
                                message;
                        }

                        return next;
                    }),
            },
        );
    };

    const stopRecording = () => {
        recordingRef.current = null;
        setRecording(null);
        setRecordingShortcut(false);
    };

    const record = (command: AppCommand, accelerator: string) => {
        stopRecording();

        if (accelerator !== bindings[command]) {
            save({ [command]: accelerator });
        }
    };

    // The listeners below are added once per recording, so they reach the latest of these here.
    const latest = useRef({ record, stopRecording, kite });

    useEffect(() => {
        latest.current = { record, stopRecording, kite };
    });

    useEffect(() => {
        if (recording === null) {
            return;
        }

        recordingRef.current = recording;
        setRecordingShortcut(true);

        // Ahead of everything else, so the keys pressed record a shortcut rather than run one.
        const onKeyDown = (event: KeyboardEvent) => {
            const command = recordingRef.current;

            if (command === null) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (event.key === 'Escape') {
                latest.current.stopRecording();

                return;
            }

            if (['Meta', 'Control', 'Alt', 'Shift'].includes(event.key)) {
                return;
            }

            const accelerator = acceleratorFor(
                event,
                latest.current.kite.platform,
            );

            if (accelerator === null) {
                setErrors((current) => ({
                    ...current,
                    [command]: `Hold ${formatShortcut('Mod', latest.current.kite.platform)} (and Alt or Shift if you like) and press a letter, number or punctuation key.`,
                }));

                return;
            }

            latest.current.record(command, accelerator);
        };

        // In the desktop app the menu takes its commands' shortcuts before the page sees them.
        const onMenuCommand = (event: Event) => {
            const command = recordingRef.current;
            const taken =
                latest.current.kite.key_bindings[
                    (event as CustomEvent<string>).detail
                ];

            if (command !== null && taken) {
                latest.current.record(command, taken);
            }
        };

        window.addEventListener('keydown', onKeyDown, { capture: true });
        window.addEventListener(RECORDED_COMMAND_EVENT, onMenuCommand);

        return () => {
            window.removeEventListener('keydown', onKeyDown, { capture: true });
            window.removeEventListener(RECORDED_COMMAND_EVENT, onMenuCommand);
            setRecordingShortcut(false);
        };
    }, [recording]);

    const onOpenChangeAndReset = (next: boolean) => {
        if (!next) {
            stopRecording();
            setCustomising(false);
            setErrors({});
        }

        onOpenChange(next);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChangeAndReset}>
            <DialogContent
                className="kite-no-drag max-h-[85vh] overflow-y-auto sm:max-w-2xl"
                data-test="shortcuts-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Keyboard shortcuts</DialogTitle>
                    <DialogDescription>
                        {customising
                            ? 'Choose a shortcut, then press the keys you want for it. The editor’s and links’ shortcuts stay as they are.'
                            : 'Most of these are also in the app menu.'}
                    </DialogDescription>
                </DialogHeader>
                {customising ? (
                    <ul className="divide-y text-sm">
                        {COMMANDS.map(({ command, label }) => (
                            <CustomisableShortcut
                                key={command}
                                command={command}
                                label={label}
                                accelerator={bindings[command] ?? ''}
                                isDefault={
                                    bindings[command] === defaults[command]
                                }
                                recording={recording === command}
                                error={errors[command]}
                                onRecord={() => {
                                    setErrors((current) => ({
                                        ...current,
                                        [command]: undefined,
                                    }));
                                    setRecording(command);
                                }}
                                onCancel={stopRecording}
                                onRemove={() => save({ [command]: '' })}
                                onReset={() => save({ [command]: null })}
                            />
                        ))}
                    </ul>
                ) : (
                    <div className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        {SECTIONS.map((section) => (
                            <section key={section.title} className="space-y-2">
                                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {section.title}
                                </h3>
                                <dl className="space-y-1.5 text-sm">
                                    {section.entries.map((entry) => {
                                        const shortcut =
                                            'command' in entry
                                                ? bindings[entry.command]
                                                : entry.shortcut;

                                        return (
                                            <div
                                                key={entry.label}
                                                className="flex items-center justify-between gap-4"
                                            >
                                                <dt>{entry.label}</dt>
                                                <dd>
                                                    {shortcut ? (
                                                        <Kbd
                                                            shortcut={shortcutText(
                                                                shortcut,
                                                            )}
                                                        />
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            None
                                                        </span>
                                                    )}
                                                </dd>
                                            </div>
                                        );
                                    })}
                                </dl>
                            </section>
                        ))}
                    </div>
                )}
                <DialogFooter className="sm:justify-between">
                    {customising ? (
                        <>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={changed.length === 0}
                                onClick={() =>
                                    save(
                                        Object.fromEntries(
                                            changed.map(({ command }) => [
                                                command,
                                                null,
                                            ]),
                                        ),
                                    )
                                }
                                data-test="reset-all-shortcuts"
                            >
                                Reset all
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => {
                                    stopRecording();
                                    setCustomising(false);
                                    setErrors({});
                                }}
                                data-test="done-customising-shortcuts"
                            >
                                Done
                            </Button>
                        </>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="sm:ml-auto"
                            onClick={() => setCustomising(true)}
                            data-test="customise-shortcuts"
                        >
                            Customise…
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CustomisableShortcut({
    command,
    label,
    accelerator,
    isDefault,
    recording,
    error,
    onRecord,
    onCancel,
    onRemove,
    onReset,
}: {
    command: AppCommand;
    label: string;
    accelerator: string;
    isDefault: boolean;
    recording: boolean;
    error?: string;
    onRecord: () => void;
    onCancel: () => void;
    onRemove: () => void;
    onReset: () => void;
}) {
    return (
        <li className="py-2" data-test={`shortcut-${command}`}>
            <div className="flex items-center gap-2">
                <span className="min-w-0 flex-1 truncate">{label}</span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn(
                        'min-w-28 justify-center font-normal',
                        recording && 'border-primary ring-2 ring-ring/40',
                        error && 'border-destructive',
                    )}
                    aria-label={
                        recording
                            ? `Press the new shortcut for ${label}, or Escape to cancel`
                            : `Change the shortcut for ${label}`
                    }
                    onClick={recording ? onCancel : onRecord}
                    data-test={`record-shortcut-${command}`}
                >
                    {recording ? (
                        <span className="text-xs text-muted-foreground">
                            Press keys…
                        </span>
                    ) : accelerator ? (
                        <Kbd
                            shortcut={shortcutText(accelerator)}
                            className="border-0 px-0 text-xs"
                        />
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            None
                        </span>
                    )}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={accelerator === ''}
                    aria-label={`Remove the shortcut for ${label}`}
                    title="Remove shortcut"
                    onClick={onRemove}
                    data-test={`remove-shortcut-${command}`}
                >
                    <X />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={isDefault}
                    aria-label={`Reset the shortcut for ${label}`}
                    title="Reset to default"
                    onClick={onReset}
                    data-test={`reset-shortcut-${command}`}
                >
                    <RotateCcw />
                </Button>
            </div>
            {error && (
                <p
                    className="mt-1 text-xs text-destructive-foreground"
                    role="alert"
                    data-test="shortcut-error"
                >
                    {error}
                </p>
            )}
        </li>
    );
}

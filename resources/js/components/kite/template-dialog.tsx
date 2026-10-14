import { router } from '@inertiajs/react';
import { FileStack, LayoutTemplate } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { store } from '@/routes/notes';
import { store as startTemplate } from '@/routes/templates';
import type { NoteTemplate, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    templates: NoteTemplate[];
    /** Folder the new note goes in, or null for the vault root. */
    folder: string | null;
    onClose: () => void;
};

const PLACEHOLDERS: [string, string][] = [
    ['{{title}}', 'the new note’s title'],
    ['{{date}}', 'today, e.g. 2026-10-14'],
    ['{{date:l j F}}', 'today in any PHP date format'],
    ['{{time}}', 'the time now'],
    ['{{yesterday}} {{tomorrow}}', 'neighbouring dates'],
    ['{{folder}} {{vault}}', 'where the note is made'],
];

/**
 * Pick a template and a title, then create the note with its placeholders filled in.
 */
export function TemplateDialog({ vault, templates, folder, onClose }: Props) {
    return (
        <Dialog
            open={folder !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent
                className="kite-no-drag sm:max-w-lg"
                data-test="template-dialog"
            >
                {folder !== null &&
                    (templates.length === 0 ? (
                        <NoTemplates vault={vault} />
                    ) : (
                        <TemplateForm
                            vault={vault}
                            templates={templates}
                            folder={folder}
                            onDone={onClose}
                        />
                    ))}
            </DialogContent>
        </Dialog>
    );
}

function TemplateForm({
    vault,
    templates,
    folder,
    onDone,
}: {
    vault: VaultSummary;
    templates: NoteTemplate[];
    folder: string;
    onDone: () => void;
}) {
    const [template, setTemplate] = useState(templates[0].path);
    const [title, setTitle] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                router.post(
                    store(vault.id).url,
                    {
                        title: title.trim() === '' ? 'Untitled' : title,
                        template,
                        ...(folder === '' ? {} : { folder }),
                    },
                    {
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onSuccess: onDone,
                        onError: setErrors,
                    },
                );
            }}
        >
            <DialogHeader>
                <DialogTitle>New note from template</DialogTitle>
                <DialogDescription>
                    {folder === ''
                        ? 'The note is created at the top of the vault.'
                        : `The note is created in ${folder}.`}
                </DialogDescription>
            </DialogHeader>

            <fieldset className="grid gap-1.5">
                <legend className="mb-1.5 text-sm font-medium">Template</legend>
                <div
                    className="grid max-h-56 gap-1 overflow-y-auto"
                    role="radiogroup"
                >
                    {templates.map((item) => (
                        <button
                            key={item.path}
                            type="button"
                            role="radio"
                            aria-checked={item.path === template}
                            onClick={() => setTemplate(item.path)}
                            data-test="template-option"
                            className={cn(
                                'flex items-center gap-2 rounded-md border px-3 py-2 text-left text-sm hover:bg-accent',
                                item.path === template &&
                                    'border-primary bg-accent',
                            )}
                        >
                            <LayoutTemplate className="size-4 shrink-0 text-muted-foreground" />
                            <span className="truncate">{item.name}</span>
                        </button>
                    ))}
                </div>
                <InputError message={errors.template} />
            </fieldset>

            <div className="grid gap-1.5">
                <Label htmlFor="template-title">Title</Label>
                <Input
                    id="template-title"
                    autoFocus
                    placeholder="Untitled"
                    value={title}
                    onChange={(event) => setTitle(event.target.value)}
                />
                <InputError message={errors.title ?? errors.folder} />
            </div>

            <details className="text-xs text-muted-foreground">
                <summary className="cursor-pointer">Placeholders</summary>
                <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                    {PLACEHOLDERS.map(([placeholder, meaning]) => (
                        <div key={placeholder} className="contents">
                            <dt className="font-mono">{placeholder}</dt>
                            <dd>{meaning}</dd>
                        </div>
                    ))}
                </dl>
            </details>

            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Create note
                </Button>
            </DialogFooter>
        </form>
    );
}

function NoTemplates({ vault }: { vault: VaultSummary }) {
    return (
        <div className="grid gap-4">
            <DialogHeader>
                <DialogTitle>No templates yet</DialogTitle>
                <DialogDescription>
                    Any note in your templates folder (set in Preferences) can
                    be used as a template. Start one with example placeholders
                    to see how they work.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    onClick={() => router.post(startTemplate(vault.id).url)}
                    data-test="start-template"
                >
                    <FileStack />
                    Create a template
                </Button>
            </DialogFooter>
        </div>
    );
}

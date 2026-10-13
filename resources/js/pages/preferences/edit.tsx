import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { update } from '@/routes/preferences';
import type { Preferences } from '@/types';

type Props = {
    preferences: Preferences;
};

const selectClass =
    'h-9 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-4">
            <h2 className="text-sm font-semibold">{title}</h2>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

export default function PreferencesEdit({ preferences }: Props) {
    const form = useForm({
        ...preferences,
        ignored_folders: preferences.ignored_folders.join('\n'),
        attachments_folder: preferences.attachments_folder ?? '',
        daily_folder: preferences.daily_folder ?? '',
        daily_template: preferences.daily_template ?? '',
        capture_shortcut: preferences.capture_shortcut ?? '',
    });

    form.transform((data) => ({
        ...data,
        ignored_folders: data.ignored_folders
            .split(/[\n,]/)
            .map((folder) => folder.trim())
            .filter(Boolean),
    }));

    const { data, setData, errors, processing } = form;
    const folderErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('ignored_folders'))
        .map(([, message]) => message)
        .join(' ');

    return (
        <>
            <Head title="Preferences" />

            <main className="min-h-svh bg-background">
                <header className="kite-drag sticky top-0 flex h-12 items-center gap-2 border-b bg-background px-4 pl-20">
                    <Link
                        href={home()}
                        className="kite-no-drag flex items-center gap-1 rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-accent"
                    >
                        <ArrowLeft className="size-4" />
                        Back to notes
                    </Link>
                </header>

                <form
                    className="mx-auto max-w-3xl space-y-10 px-6 py-10"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update().url, { preserveScroll: true });
                    }}
                >
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Preferences
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Stored locally and applied to every vault.
                        </p>
                    </div>

                    <Section title="Editor">
                        <Field
                            id="editor_font"
                            label="Font"
                            error={errors.editor_font}
                        >
                            <select
                                id="editor_font"
                                className={selectClass}
                                value={data.editor_font}
                                onChange={(event) =>
                                    setData(
                                        'editor_font',
                                        event.target
                                            .value as Preferences['editor_font'],
                                    )
                                }
                            >
                                <option value="mono">Monospace</option>
                                <option value="sans">Sans serif</option>
                                <option value="serif">Serif</option>
                            </select>
                        </Field>
                        <Field
                            id="editor_font_size"
                            label="Font size"
                            error={errors.editor_font_size}
                        >
                            <Input
                                id="editor_font_size"
                                type="number"
                                min={12}
                                max={22}
                                value={data.editor_font_size}
                                onChange={(event) =>
                                    setData(
                                        'editor_font_size',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="editor_width"
                            label="Line width"
                            error={errors.editor_width}
                        >
                            <select
                                id="editor_width"
                                className={selectClass}
                                value={data.editor_width}
                                onChange={(event) =>
                                    setData(
                                        'editor_width',
                                        event.target
                                            .value as Preferences['editor_width'],
                                    )
                                }
                            >
                                <option value="narrow">Narrow</option>
                                <option value="normal">Normal</option>
                                <option value="wide">Wide</option>
                                <option value="full">Full width</option>
                            </select>
                        </Field>
                        <Field
                            id="autosave_ms"
                            label="Autosave delay (ms)"
                            hint="How long to wait after typing stops before saving."
                            error={errors.autosave_ms}
                        >
                            <Input
                                id="autosave_ms"
                                type="number"
                                min={200}
                                max={10000}
                                step={100}
                                value={data.autosave_ms}
                                onChange={(event) =>
                                    setData(
                                        'autosave_ms',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </Field>
                    </Section>

                    <Section title="Vault files">
                        <Field
                            id="ignored_folders"
                            label="Ignored folders"
                            hint="One per line. Dot-folders like .git are always ignored."
                            error={folderErrors || undefined}
                        >
                            <textarea
                                id="ignored_folders"
                                rows={4}
                                className="w-full rounded-md border bg-background px-3 py-2 font-mono text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                value={data.ignored_folders}
                                onChange={(event) =>
                                    setData(
                                        'ignored_folders',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="attachments_folder"
                            label="Attachments folder"
                            hint="Where pasted and dropped files are saved."
                            error={errors.attachments_folder}
                        >
                            <Input
                                id="attachments_folder"
                                value={data.attachments_folder}
                                onChange={(event) =>
                                    setData(
                                        'attachments_folder',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </Section>

                    <Section title="Daily notes">
                        <Field
                            id="daily_folder"
                            label="Folder"
                            error={errors.daily_folder}
                        >
                            <Input
                                id="daily_folder"
                                value={data.daily_folder}
                                onChange={(event) =>
                                    setData('daily_folder', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="daily_format"
                            label="File name format"
                            hint="PHP date format, e.g. Y-m-d."
                            error={errors.daily_format}
                        >
                            <Input
                                id="daily_format"
                                className="font-mono"
                                value={data.daily_format}
                                onChange={(event) =>
                                    setData('daily_format', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="daily_template"
                            label="Template note"
                            hint="Vault-relative path. Supports {{date}}, {{title}} and {{time}}."
                            error={errors.daily_template}
                        >
                            <Input
                                id="daily_template"
                                placeholder="Templates/Daily.md"
                                value={data.daily_template}
                                onChange={(event) =>
                                    setData(
                                        'daily_template',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </Section>

                    <Section title="Quick capture">
                        <Field
                            id="capture_shortcut"
                            label="Global shortcut"
                            hint="Electron accelerator, e.g. CmdOrCtrl+Shift+Space. Leave empty to disable."
                            error={errors.capture_shortcut}
                        >
                            <Input
                                id="capture_shortcut"
                                className="font-mono"
                                value={data.capture_shortcut}
                                onChange={(event) =>
                                    setData(
                                        'capture_shortcut',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </Section>

                    <div className="flex justify-end border-t pt-6">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Save preferences
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}

import { router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    FolderTree,
    Globe,
    LayoutTemplate,
    ListTree,
    Monitor,
    Moon,
    Palette,
    Sun,
    Type,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { update } from '@/routes/preferences';
import type { Preferences } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const selectClass = 'kite-select h-9 w-full text-sm';

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
        <div className="grid content-start gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

type SectionId = (typeof SECTIONS)[number]['title'];

const SECTIONS = [
    { title: 'Appearance', icon: Palette },
    { title: 'Editor', icon: Type },
    { title: 'Vault files', icon: FolderTree },
    { title: 'Daily notes', icon: CalendarDays },
    { title: 'Templates', icon: LayoutTemplate },
    { title: 'Publishing', icon: Globe },
    { title: 'File tree', icon: ListTree },
    { title: 'Quick capture', icon: Zap },
] as const;

function sectionId(title: string): string {
    return `preferences-${title.toLowerCase().replace(/\s+/g, '-')}`;
}

function Section({
    title,
    children,
}: {
    title: SectionId;
    children: ReactNode;
}) {
    const Icon = SECTIONS.find((section) => section.title === title)?.icon;

    return (
        <section id={sectionId(title)} className="scroll-mt-6 space-y-3">
            <h3 className="flex items-center gap-2 text-[13.5px] font-semibold">
                {Icon && (
                    <span className="flex size-6 items-center justify-center rounded-md bg-kite-accent-soft text-primary">
                        <Icon className="size-3.5" />
                    </span>
                )}
                {title}
            </h3>
            <div className="grid gap-4 rounded-xl border bg-card p-4 shadow-kite-sm sm:grid-cols-2">
                {children}
            </div>
        </section>
    );
}

/**
 * Light, dark or system, picked from small previews of each.
 */
function ThemeCards() {
    const { appearance, updateAppearance } = useAppearance();
    const options: { value: Appearance; label: string; icon: typeof Sun }[] = [
        { value: 'light', label: 'Light', icon: Sun },
        { value: 'dark', label: 'Dark', icon: Moon },
        { value: 'system', label: 'System', icon: Monitor },
    ];

    return (
        <div className="grid grid-cols-3 gap-3" role="radiogroup">
            {options.map(({ value, label, icon: Icon }) => (
                <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={appearance === value}
                    data-test={`appearance-${value}`}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'group rounded-xl border p-1.5 text-left transition-all hover:border-primary/40',
                        appearance === value &&
                            'border-primary ring-2 ring-primary/20',
                    )}
                >
                    <ThemePreview mode={value} />
                    <span className="mt-1.5 flex items-center gap-1.5 px-1 pb-0.5 text-[13px] font-medium">
                        <Icon className="size-3.5 text-muted-foreground" />
                        {label}
                    </span>
                </button>
            ))}
        </div>
    );
}

function ThemePreview({ mode }: { mode: Appearance }) {
    const pane = (dark: boolean) => (
        <div
            className={cn(
                'flex h-full flex-1 gap-1 p-1.5',
                dark
                    ? 'bg-[oklch(0.205_0.006_285)]'
                    : 'bg-[oklch(0.995_0.0015_90)]',
            )}
        >
            <div
                className={cn(
                    'w-1/3 space-y-1 rounded-sm p-1',
                    dark
                        ? 'bg-[oklch(0.18_0.006_285)]'
                        : 'bg-[oklch(0.965_0.004_85)]',
                )}
            >
                <div className="h-1 w-3/4 rounded-full bg-[oklch(0.53_0.2_278)]" />
                <div
                    className={cn(
                        'h-1 w-1/2 rounded-full',
                        dark ? 'bg-white/20' : 'bg-black/15',
                    )}
                />
            </div>
            <div className="flex-1 space-y-1 pt-0.5">
                <div
                    className={cn(
                        'h-1.5 w-2/3 rounded-full',
                        dark ? 'bg-white/60' : 'bg-black/60',
                    )}
                />
                {[0, 1].map((line) => (
                    <div
                        key={line}
                        className={cn(
                            'h-1 rounded-full',
                            dark ? 'bg-white/20' : 'bg-black/15',
                        )}
                    />
                ))}
            </div>
        </div>
    );

    return (
        <div className="flex h-16 overflow-hidden rounded-lg border">
            {mode === 'system' ? (
                <>
                    {pane(false)}
                    {pane(true)}
                </>
            ) : (
                pane(mode === 'dark')
            )}
        </div>
    );
}

/**
 * Preferences in a modal over the workspace. The full settings are an optional Inertia prop,
 * fetched fresh each time the modal opens so the form never starts from stale values.
 */
export function PreferencesDialog({ open, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag flex h-[85vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-4xl"
                data-test="preferences-dialog"
            >
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>Preferences</DialogTitle>
                    <DialogDescription>
                        Stored locally and applied to every vault.
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <PreferencesLoader onSaved={() => onOpenChange(false)} />
                )}
            </DialogContent>
        </Dialog>
    );
}

function PreferencesLoader({ onSaved }: { onSaved: () => void }) {
    const [preferences, setPreferences] = useState<Preferences | null>(null);

    useEffect(() => {
        router.reload({
            only: ['preferences'],
            onSuccess: (page) => {
                const loaded = page.props.preferences as
                    | Preferences
                    | undefined;

                if (loaded) {
                    setPreferences(loaded);
                }
            },
        });
    }, []);

    if (!preferences) {
        return (
            <div className="space-y-6 px-6 py-6" aria-busy="true">
                {[0, 1, 2].map((section) => (
                    <div key={section} className="space-y-3">
                        <Skeleton className="h-4 w-24" />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Skeleton className="h-9" />
                            <Skeleton className="h-9" />
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    return <PreferencesForm preferences={preferences} onSaved={onSaved} />;
}

function PreferencesForm({
    preferences,
    onSaved,
}: {
    preferences: Preferences;
    onSaved: () => void;
}) {
    const form = useForm({
        ...preferences,
        ignored_folders: preferences.ignored_folders.join('\n'),
        attachments_folder: preferences.attachments_folder ?? '',
        daily_folder: preferences.daily_folder ?? '',
        daily_template: preferences.daily_template ?? '',
        templates_folder: preferences.templates_folder ?? '',
        publish_folder: preferences.publish_folder ?? '',
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
    const [activeSection, setActiveSection] = useState<SectionId>('Appearance');
    const folderErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('ignored_folders'))
        .map(([, message]) => message)
        .join(' ');

    return (
        <form
            className="flex min-h-0 flex-1 flex-col"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(update().url, {
                    only: ['kite'],
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: onSaved,
                });
            }}
        >
            <div className="flex min-h-0 flex-1">
                <nav
                    className="hidden w-48 shrink-0 space-y-px border-r bg-sidebar/50 p-2 sm:block"
                    aria-label="Preference sections"
                >
                    {SECTIONS.map(({ title, icon: Icon }) => (
                        <button
                            key={title}
                            type="button"
                            onClick={() => {
                                setActiveSection(title);
                                document
                                    .getElementById(sectionId(title))
                                    ?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            aria-current={activeSection === title}
                            className={cn(
                                'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[13px] text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground',
                                activeSection === title &&
                                    'bg-sidebar-accent font-medium text-foreground',
                            )}
                        >
                            <Icon className="size-4" />
                            {title}
                        </button>
                    ))}
                </nav>
                <div
                    className="min-h-0 flex-1 space-y-8 overflow-y-auto px-6 py-6"
                    onScroll={(event) => {
                        const top = event.currentTarget.scrollTop;
                        const current = [...SECTIONS]
                            .reverse()
                            .find(
                                ({ title }) =>
                                    (document.getElementById(sectionId(title))
                                        ?.offsetTop ?? 0) -
                                        48 <=
                                    top,
                            );

                        if (current) {
                            setActiveSection(current.title);
                        }
                    }}
                >
                    <Section title="Appearance">
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label>Theme</Label>
                            <ThemeCards />
                            <p className="text-xs text-muted-foreground">
                                Applies straight away. System follows your OS
                                setting.
                            </p>
                        </div>
                    </Section>

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
                        <div className="flex items-start gap-2 sm:col-span-2">
                            <Checkbox
                                id="live_preview"
                                checked={data.live_preview}
                                onCheckedChange={(value) =>
                                    setData('live_preview', value === true)
                                }
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="live_preview">
                                    Live preview
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Hide markdown symbols on lines you aren’t
                                    editing, and show checkboxes and bullets.
                                </p>
                            </div>
                        </div>
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
                            hint="Vault-relative path. Supports the same placeholders as templates."
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

                    <Section title="Templates">
                        <Field
                            id="templates_folder"
                            label="Templates folder"
                            hint="Notes here are offered by New note from template. Leave empty to turn templates off."
                            error={errors.templates_folder}
                        >
                            <Input
                                id="templates_folder"
                                placeholder="Templates"
                                value={data.templates_folder}
                                onChange={(event) =>
                                    setData(
                                        'templates_folder',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </Section>

                    <Section title="Publishing">
                        <Field
                            id="publish_folder"
                            label="Publish folder"
                            hint="Absolute path. Published notes go in a subfolder per vault and note. Empty uses Kite Published in your home folder."
                            error={errors.publish_folder}
                        >
                            <Input
                                id="publish_folder"
                                placeholder="~/Kite Published"
                                className="font-mono text-sm"
                                value={data.publish_folder}
                                onChange={(event) =>
                                    setData(
                                        'publish_folder',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </Section>

                    <Section title="File tree">
                        <div className="flex items-start gap-2 sm:col-span-2">
                            <Checkbox
                                id="tree_group_colours"
                                checked={data.tree_group_colours}
                                onCheckedChange={(value) =>
                                    setData(
                                        'tree_group_colours',
                                        value === true,
                                    )
                                }
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="tree_group_colours">
                                    Show colour group dots
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Mark notes with the colour of the first
                                    colour group they match, as on the graph.
                                </p>
                            </div>
                        </div>
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
                </div>
            </div>

            <DialogFooter className="border-t bg-muted/30 px-6 py-3">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Save preferences
                </Button>
            </DialogFooter>
        </form>
    );
}

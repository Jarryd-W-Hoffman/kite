import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    CheckSquare,
    FilePlus2,
    FileText,
    LifeBuoy,
    Keyboard,
    Network,
    Search,
    Sparkles,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { CommandPalette } from '@/components/kite/command-palette';
import { Kbd } from '@/components/kite/kbd';
import { TabBar } from '@/components/kite/tab-bar';
import { WorkspaceSidebar } from '@/components/kite/workspace-sidebar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { runAppCommand } from '@/hooks/use-app-commands';
import type { AppCommand } from '@/hooks/use-app-commands';
import { useOpenExternalLink } from '@/hooks/use-open-external-link';
import { useVaultSync } from '@/hooks/use-vault-sync';
import {
    shortcutText,
    tipsWithShortcuts,
    useShortcuts,
} from '@/lib/key-bindings';
import { requestNewTab } from '@/lib/tabs';
import { update as updateCrashReportConsent } from '@/routes/crash-report-consent';
import { show as showNote } from '@/routes/notes';
import { index as tasksIndex } from '@/routes/tasks';
import { show as showGraph } from '@/routes/vault-graph';
import { update as updateWelcome } from '@/routes/welcome';
import type { NoteLinkSummary, WorkspaceProps } from '@/types';

type Props = WorkspaceProps & {
    version: string;
    /** Kite has just been updated to this version. */
    updated: boolean;
    /** This version's changelog entry, as HTML. */
    whats_new: string | null;
    release_notes_url: string;
    welcome_on_startup: boolean;
    /** The quick capture shortcut set in Preferences, e.g. `CmdOrCtrl+Shift+Space`; empty when it's off. */
    capture_shortcut: string;
    /** Whether to ask, once, about sending crash reports. */
    ask_crash_reports: boolean;
    recent_notes?: NoteLinkSummary[];
};

const TIPS: {
    shortcut: string;
    command?: AppCommand;
    /** The quick capture tip, which shows the shortcut set in Preferences. */
    quickCapture?: boolean;
    text: string;
}[] = [
    {
        shortcut: 'Mod+K',
        command: 'search',
        text: 'Search every note, or type > for commands',
    },
    { shortcut: '[[', text: 'Link to another note as you write' },
    {
        shortcut: 'Mod+Shift+Space',
        quickCapture: true,
        text: 'Quick capture from anywhere',
    },
    { shortcut: 'Mod+D', command: 'daily-note', text: "Open today's note" },
    {
        shortcut: 'Mod+/',
        command: 'shortcuts',
        text: 'See every keyboard shortcut',
    },
];

/**
 * Tips with the quick capture shortcut set in Preferences, leaving out its tip when it's off.
 */
function withCaptureShortcut<
    Tip extends { shortcut: string; quickCapture?: boolean },
>(tips: Tip[], captureShortcut: string): Tip[] {
    return tips.flatMap((tip) => {
        if (!tip.quickCapture) {
            return [tip];
        }

        return captureShortcut.trim() === ''
            ? []
            : [{ ...tip, shortcut: shortcutText(captureShortcut) }];
    });
}

export default function WelcomeShow({
    vault,
    vaults,
    tree,
    folders,
    colour_groups: colourGroups,
    tags,
    indexing,
    watching,
    version,
    updated,
    whats_new: whatsNew,
    release_notes_url: releaseNotesUrl,
    welcome_on_startup: welcomeOnStartup,
    capture_shortcut: captureShortcut,
    ask_crash_reports: askCrashReports,
    recent_notes: recentNotes = [],
}: Props) {
    const shortcutFor = useShortcuts();
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [onStartup, setOnStartup] = useState(welcomeOnStartup);
    const openExternalLink = useOpenExternalLink();

    useVaultSync(
        vault,
        () => router.reload({ only: ['tree', 'folders', 'tags'] }),
        watching,
    );

    // Notes opened from here get their own tab, so Welcome stays open beside them.
    const inNewTab = (command: AppCommand) => () => {
        requestNewTab();
        runAppCommand(command);
    };

    const answerCrashReports = (send: boolean) =>
        router.put(
            updateCrashReportConsent().url,
            { crash_reports: send },
            { preserveScroll: true, only: ['ask_crash_reports'] },
        );

    const toggleOnStartup = (value: boolean) => {
        setOnStartup(value);
        router.put(
            updateWelcome().url,
            { welcome_on_startup: value },
            {
                preserveScroll: true,
                preserveState: true,
                only: [],
                onError: () => setOnStartup(!value),
            },
        );
    };

    return (
        <>
            <Head title={`Welcome · ${vault.name}`} />

            <div className="flex h-svh overflow-hidden bg-background">
                <WorkspaceSidebar
                    vault={vault}
                    vaults={vaults}
                    tree={tree}
                    folders={folders}
                    colourGroups={colourGroups}
                    tags={tags}
                    activeNoteId={null}
                    indexing={indexing}
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="flex min-w-0 flex-1 flex-col">
                    <TabBar
                        vault={vault}
                        notes={tree}
                        current={{
                            kind: 'page',
                            page: 'welcome',
                            title: 'Welcome',
                        }}
                    />
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-4">
                        <h1 className="flex items-center gap-2 text-[13.5px] font-semibold">
                            <Sparkles className="size-4 text-primary" />
                            Welcome
                        </h1>
                    </header>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        <div className="mx-auto grid max-w-3xl gap-10 px-8 py-10">
                            {updated ? (
                                <section
                                    aria-labelledby="whats-new"
                                    className="rounded-xl border border-primary/30 bg-primary/5 p-5"
                                    data-test="whats-new"
                                >
                                    <h2
                                        id="whats-new"
                                        className="flex items-center gap-2 text-base font-semibold"
                                    >
                                        <Sparkles className="size-4 text-primary" />
                                        Kite has been updated to {version}
                                    </h2>
                                    {whatsNew === null ? (
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            See what&apos;s changed in this
                                            version.
                                        </p>
                                    ) : (
                                        <ChangelogEntry
                                            html={whatsNew}
                                            onOpenLink={openExternalLink}
                                        />
                                    )}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="mt-4"
                                        onClick={() =>
                                            openExternalLink(releaseNotesUrl)
                                        }
                                    >
                                        All release notes
                                    </Button>
                                </section>
                            ) : (
                                <div className="grid gap-2">
                                    <p
                                        className="text-sm text-muted-foreground"
                                        data-test="version"
                                    >
                                        Kite {version} ·{' '}
                                        <button
                                            type="button"
                                            className="text-primary hover:underline"
                                            onClick={() =>
                                                openExternalLink(
                                                    releaseNotesUrl,
                                                )
                                            }
                                        >
                                            Release notes
                                        </button>
                                    </p>
                                    {whatsNew !== null && (
                                        <details
                                            className="group rounded-lg border px-4 py-2"
                                            data-test="whats-new-details"
                                        >
                                            <summary className="cursor-pointer text-sm font-medium">
                                                What&apos;s new in {version}
                                            </summary>
                                            <ChangelogEntry
                                                html={whatsNew}
                                                onOpenLink={openExternalLink}
                                            />
                                        </details>
                                    )}
                                </div>
                            )}

                            {askCrashReports && (
                                <section
                                    aria-labelledby="crash-reports"
                                    className="rounded-xl border p-5"
                                    data-test="crash-reports-ask"
                                >
                                    <h2
                                        id="crash-reports"
                                        className="flex items-center gap-2 text-base font-semibold"
                                    >
                                        <LifeBuoy className="size-4 text-primary" />
                                        Help make Kite better
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Send a report when something goes wrong,
                                        so it can be fixed. Reports include the
                                        error and where in Kite&apos;s code it
                                        happened. Your notes are never included,
                                        and folder paths are removed. You can
                                        change this any time in Preferences.
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                answerCrashReports(true)
                                            }
                                        >
                                            Send crash reports
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                answerCrashReports(false)
                                            }
                                        >
                                            No thanks
                                        </Button>
                                    </div>
                                </section>
                            )}

                            <Section title="Start">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <Action
                                        icon={FilePlus2}
                                        label="New note"
                                        shortcut="Mod+N"
                                        onClick={inNewTab('new-note')}
                                    />
                                    <Action
                                        icon={CalendarDays}
                                        label="Today's note"
                                        shortcut="Mod+D"
                                        onClick={inNewTab('daily-note')}
                                    />
                                    <Action
                                        icon={Search}
                                        label="Search notes"
                                        shortcut="Mod+K"
                                        onClick={() => setPaletteOpen(true)}
                                    />
                                    <Action
                                        icon={Network}
                                        label="Graph view"
                                        shortcut="Mod+G"
                                        onClick={() =>
                                            router.visit(showGraph(vault.id))
                                        }
                                    />
                                    <Action
                                        icon={CheckSquare}
                                        label="Tasks"
                                        onClick={() =>
                                            router.visit(tasksIndex(vault.id))
                                        }
                                    />
                                    <Action
                                        icon={Keyboard}
                                        label="Keyboard shortcuts"
                                        shortcut="Mod+/"
                                        onClick={() =>
                                            runAppCommand('shortcuts')
                                        }
                                    />
                                </div>
                            </Section>

                            <Section title="Recent notes">
                                {recentNotes.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Notes you open will show here.
                                    </p>
                                ) : (
                                    <ul
                                        className="grid gap-0.5"
                                        data-test="welcome-recent"
                                    >
                                        {recentNotes.map((note) => (
                                            <li key={note.id}>
                                                <Link
                                                    href={showNote({
                                                        vault: vault.id,
                                                        note: note.id,
                                                    })}
                                                    onClick={requestNewTab}
                                                    className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                                                >
                                                    <FileText className="size-4 shrink-0 text-muted-foreground" />
                                                    <span className="truncate">
                                                        {note.title}
                                                    </span>
                                                    <span className="ml-auto truncate text-xs text-muted-foreground">
                                                        {note.path}
                                                    </span>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Section>

                            <Section title="Tips">
                                <ul className="grid gap-2 text-sm">
                                    {tipsWithShortcuts(
                                        withCaptureShortcut(
                                            TIPS,
                                            captureShortcut,
                                        ),
                                        shortcutFor,
                                    ).map((tip) => (
                                        <li
                                            key={tip.text}
                                            className="flex items-center gap-3"
                                        >
                                            <Kbd shortcut={tip.shortcut} />
                                            <span className="text-muted-foreground">
                                                {tip.text}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </Section>

                            <div className="flex items-center gap-2 border-t pt-6">
                                <Checkbox
                                    id="welcome_on_startup"
                                    checked={onStartup}
                                    onCheckedChange={(value) =>
                                        toggleOnStartup(value === true)
                                    }
                                />
                                <Label htmlFor="welcome_on_startup">
                                    Show the Welcome tab when Kite starts
                                </Label>
                            </div>
                        </div>
                    </div>
                </main>
            </div>

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
            />
        </>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section aria-label={title} className="grid gap-3">
            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </h2>
            {children}
        </section>
    );
}

function Action({
    icon: Icon,
    label,
    shortcut,
    onClick,
}: {
    icon: LucideIcon;
    label: string;
    shortcut?: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition-colors hover:bg-accent"
        >
            <Icon className="size-4 shrink-0 text-primary" />
            <span className="flex-1">{label}</span>
            {shortcut && <Kbd shortcut={shortcut} />}
        </button>
    );
}

/**
 * A changelog entry, rendered by the server with its HTML escaped. Links in it open in the system
 * browser rather than the app window.
 */
function ChangelogEntry({
    html,
    onOpenLink,
}: {
    html: string;
    onOpenLink: (url: string) => void;
}) {
    return (
        <div
            className="kite-prose mt-3"
            style={{ fontSize: 14, lineHeight: 1.6 }}
            data-test="changelog-entry"
            onClick={(event) => {
                const anchor = (event.target as HTMLElement).closest('a');
                const href = anchor?.getAttribute('href') ?? '';

                if (/^https?:\/\//i.test(href)) {
                    event.preventDefault();
                    onOpenLink(href);
                }
            }}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

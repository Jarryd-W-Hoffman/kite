import type { ColourGroup } from '@/lib/colour-groups';

export type VaultSummary = {
    id: number;
    name: string;
    path: string;
};

export type TreeNote = {
    id: number;
    path: string;
    title: string;
    /** First colour group the note matches, if any. */
    group?: number | null;
    locked?: boolean;
};

export type TagCount = {
    name: string;
    count: number;
};

export type OutlineItem = {
    level: number;
    text: string;
    slug: string;
    line: number;
};

export type NoteMeta = {
    id: number;
    path: string;
    title: string;
    frontmatter: Record<string, unknown>;
    word_count: number;
    hash: string;
    modified_at: number;
    tags: string[];
    daily_date: string | null;
    html: string;
    outline: OutlineItem[];
    /** Returned by a save: the vault's tree revision afterwards. */
    tree_revision?: number;
};

export type NoteDetail = NoteMeta & {
    contents: string;
    starred?: boolean;
    /** The note is encrypted on disk. */
    locked?: boolean;
    /** A locked note opened with the vault's key; its contents are the real text. */
    unlocked?: boolean;
    /** Copies a sync app left beside this note when it changed in two places at once. */
    conflict_copies?: NoteLinkSummary[];
    /** The note this one is a sync app's conflicting copy of. */
    conflict_of?: NoteLinkSummary | null;
};

export type NoteLinkSummary = { id: number; title: string; path: string };

export type Backlink = {
    id: number;
    title: string;
    path: string;
    line: number;
    context: string;
};

export type OutgoingLink = {
    target: string;
    note_id: number | null;
    title: string | null;
    path: string | null;
};

export type IndexingProgress = {
    done: number;
    total: number;
};

export type WorkspaceProps = {
    indexing: IndexingProgress | null;
    watching: boolean;
    vault: VaultSummary;
    vaults: VaultSummary[];
    /** Changes whenever the tree, folders, tags, templates or daily notes do. */
    tree_revision: number;
    tree: TreeNote[];
    folders: string[];
    colour_groups: ColourGroup[];
    tags: TagCount[];
    note: NoteDetail | null;
    backlinks: Backlink[];
    outgoing: OutgoingLink[];
    mentions?: UnlinkedMention[];
    comments?: NoteComment[];
    /** Set when the note's comments file exists but can't be read. */
    comments_error?: string;
    templates?: NoteTemplate[];
    /** The vault has a passphrase for locked notes. */
    passphrase_set?: boolean;
    /** The vault's passphrase has a recovery key (vaults locked before recovery keys may not). */
    recovery_key_set?: boolean;
};

export type NoteTemplate = { id: number; path: string; name: string };

export type NoteComment = {
    id: string;
    body: string;
    quote: string;
    prefix: string;
    suffix: string;
    resolved: boolean;
    created_at: string;
    updated_at: string;
};

export type UnlinkedMention = {
    id: number;
    title: string;
    path: string;
    line: number;
    term: string;
    context: string;
};

export type SearchResult = {
    id: number;
    title: string;
    path: string;
    snippet: string;
};

export type SyncSummary = {
    added: number;
    updated: number;
    removed: number;
    moved: number;
    attachments: number;
    changed: boolean;
    indexing: IndexingProgress | null;
};

export type EditorPreferences = {
    editor_font: 'sans' | 'serif' | 'mono';
    editor_font_size: number;
    editor_width: 'narrow' | 'normal' | 'wide' | 'full';
    autosave_ms: number;
    live_preview: boolean;
};

export type Preferences = EditorPreferences & {
    ignored_folders: string[];
    attachments_folder: string | null;
    daily_folder: string | null;
    daily_format: string;
    daily_template: string | null;
    templates_folder: string | null;
    publish_folder: string | null;
    capture_shortcut: string | null;
    tree_group_colours: boolean;
    welcome_on_startup: boolean;
    whats_new_after_update: boolean;
    crash_reports: boolean;
};

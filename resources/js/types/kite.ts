export type VaultSummary = {
    id: number;
    name: string;
    path: string;
};

export type TreeNote = {
    id: number;
    path: string;
    title: string;
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
};

export type NoteDetail = NoteMeta & {
    contents: string;
};

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
    tree: TreeNote[];
    tags: TagCount[];
    note: NoteDetail | null;
    backlinks: Backlink[];
    outgoing: OutgoingLink[];
    mentions?: UnlinkedMention[];
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
    attachments: number;
    changed: boolean;
    indexing: IndexingProgress | null;
};

export type EditorPreferences = {
    editor_font: 'sans' | 'serif' | 'mono';
    editor_font_size: number;
    editor_width: 'narrow' | 'normal' | 'wide' | 'full';
    autosave_ms: number;
};

export type Preferences = EditorPreferences & {
    ignored_folders: string[];
    attachments_folder: string | null;
    daily_folder: string | null;
    daily_format: string;
    daily_template: string | null;
    capture_shortcut: string | null;
};

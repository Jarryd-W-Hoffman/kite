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

export type NoteMeta = {
    id: number;
    path: string;
    title: string;
    frontmatter: Record<string, unknown>;
    word_count: number;
    hash: string;
    modified_at: number;
    tags: string[];
    html: string;
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

export type WorkspaceProps = {
    vault: VaultSummary;
    vaults: VaultSummary[];
    tree: TreeNote[];
    tags: TagCount[];
    note: NoteDetail | null;
    backlinks: Backlink[];
    outgoing: OutgoingLink[];
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
    changed: boolean;
};

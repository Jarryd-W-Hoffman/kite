import type {
    Completion,
    CompletionContext,
    CompletionResult,
} from '@codemirror/autocomplete';
import { EditorSelection } from '@codemirror/state';
import type { EditorView } from '@codemirror/view';

type Insert = {
    /** Text to put in place of the typed `/command`. */
    text: string;
    /** Where the cursor goes, as an offset into `text` (defaults to the end). */
    cursor?: number;
    /** Start the insert on its own line when the command was typed mid-line. */
    block?: boolean;
};

export type SlashCompletion = Completion & { kiteIcon?: string };

export type SlashCommand = {
    id: string;
    label: string;
    section: 'Blocks' | 'Insert' | 'Callouts' | 'Templates';
    description: string;
    /** Extra words the filter matches. */
    keywords?: string;
    icon: string;
    insert:
        | Insert
        | ((view: EditorView) => Promise<Insert | null> | Insert | null);
};

const today = (): string => {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

const time = (): string =>
    new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });

const callout = (
    type: string,
    label: string,
    icon: string,
    description: string,
): SlashCommand => ({
    id: `callout-${type}`,
    label,
    section: 'Callouts',
    description,
    keywords: `callout admonition ${type}`,
    icon,
    insert: { text: `> [!${type}]\n> `, block: true },
});

export const BASE_COMMANDS: SlashCommand[] = [
    {
        id: 'h1',
        label: 'Heading 1',
        section: 'Blocks',
        description: 'Big section heading',
        keywords: 'title h1',
        icon: 'H1',
        insert: { text: '# ', block: true },
    },
    {
        id: 'h2',
        label: 'Heading 2',
        section: 'Blocks',
        description: 'Medium section heading',
        keywords: 'subtitle h2',
        icon: 'H2',
        insert: { text: '## ', block: true },
    },
    {
        id: 'h3',
        label: 'Heading 3',
        section: 'Blocks',
        description: 'Small section heading',
        keywords: 'h3',
        icon: 'H3',
        insert: { text: '### ', block: true },
    },
    {
        id: 'bullet',
        label: 'Bulleted list',
        section: 'Blocks',
        description: 'A simple list',
        keywords: 'ul unordered',
        icon: '•',
        insert: { text: '- ', block: true },
    },
    {
        id: 'numbered',
        label: 'Numbered list',
        section: 'Blocks',
        description: 'A list with numbers',
        keywords: 'ol ordered',
        icon: '1.',
        insert: { text: '1. ', block: true },
    },
    {
        id: 'task',
        label: 'To-do',
        section: 'Blocks',
        description: 'A task with a checkbox',
        keywords: 'checkbox task todo',
        icon: '☐',
        insert: { text: '- [ ] ', block: true },
    },
    {
        id: 'quote',
        label: 'Quote',
        section: 'Blocks',
        description: 'Set text apart',
        keywords: 'blockquote',
        icon: '❝',
        insert: { text: '> ', block: true },
    },
    {
        id: 'code',
        label: 'Code block',
        section: 'Blocks',
        description: 'Code with syntax',
        keywords: 'fence snippet',
        icon: '</>',
        insert: { text: '```\n\n```', cursor: 3, block: true },
    },
    {
        id: 'table',
        label: 'Table',
        section: 'Blocks',
        description: 'Rows and columns',
        keywords: 'grid',
        icon: '▦',
        insert: {
            text: '| Column | Column |\n| --- | --- |\n|  |  |',
            cursor: 2,
            block: true,
        },
    },
    {
        id: 'divider',
        label: 'Divider',
        section: 'Blocks',
        description: 'A horizontal rule',
        keywords: 'hr line separator',
        icon: '—',
        insert: { text: '---\n', block: true },
    },
    {
        id: 'math',
        label: 'Math block',
        section: 'Blocks',
        description: 'A KaTeX formula',
        keywords: 'latex katex equation',
        icon: '∑',
        insert: { text: '$$\n\n$$', cursor: 3, block: true },
    },
    {
        id: 'mermaid',
        label: 'Diagram',
        section: 'Blocks',
        description: 'A Mermaid flowchart',
        keywords: 'mermaid chart flowchart graph',
        icon: '◇',
        insert: {
            text: '```mermaid\nflowchart LR\n  A --> B\n```',
            cursor: 23,
            block: true,
        },
    },
    callout('note', 'Note callout', 'ℹ', 'Highlight something worth knowing'),
    callout('tip', 'Tip callout', '✦', 'A helpful suggestion'),
    callout('warning', 'Warning callout', '⚠', 'Something to be careful about'),
    callout('important', 'Important callout', '!', 'Don’t miss this'),
    {
        id: 'link',
        label: 'Link to note',
        section: 'Insert',
        description: 'Start a [[wiki link]]',
        keywords: 'wiki reference',
        icon: '[[',
        insert: { text: '[[]]', cursor: 2 },
    },
    {
        id: 'date',
        label: 'Today’s date',
        section: 'Insert',
        description: 'Insert today, e.g. 2026-10-14',
        keywords: 'now day',
        icon: '📅',
        insert: () => ({ text: today() }),
    },
    {
        id: 'time',
        label: 'Current time',
        section: 'Insert',
        description: 'Insert the time, e.g. 14:05',
        keywords: 'now clock',
        icon: '🕑',
        insert: () => ({ text: time() }),
    },
    {
        id: 'tag',
        label: 'Tag',
        section: 'Insert',
        description: 'Start a #tag',
        keywords: 'label',
        icon: '#',
        insert: { text: '#' },
    },
];

/**
 * Completions for `/` typed at the start of a line or after a space: Notion-style block commands,
 * filtered by their names and keywords.
 */
export function slashCompletions(commands: () => SlashCommand[]) {
    return (context: CompletionContext): CompletionResult | null => {
        const match = context.matchBefore(/(?:^|\s)\/[\w-]*$/);

        if (!match) {
            return null;
        }

        const slash = match.from + match.text.indexOf('/');
        const query = context.state
            .sliceDoc(slash + 1, context.pos)
            .toLowerCase();
        const matching = commands().filter((command) =>
            `${command.label} ${command.keywords ?? ''}`
                .toLowerCase()
                .split(/\s+/)
                .some((word) => word.startsWith(query)),
        );

        if (matching.length === 0) {
            return null;
        }

        return {
            from: slash,
            filter: false,
            options: matching.map((command): SlashCompletion => ({
                kiteIcon: command.icon,
                label: command.label,
                detail: command.description,
                section: command.section,
                type: 'kite-slash',
                info: undefined,
                apply: (view, _completion, from, to) => {
                    void applyCommand(view, command, from, to);
                },
            })),
        };
    };
}

/** A line holding only a list, task or quote marker. */
const EMPTY_MARKER = /^\s*(?:(?:[-*+]|\d+[.)])\s+(?:\[[ xX]\]\s*)?|>\s*)$/;

async function applyCommand(
    view: EditorView,
    command: SlashCommand,
    from: number,
    to: number,
): Promise<void> {
    // Clear the typed command first, so slow commands (templates) don't leave it behind.
    view.dispatch({
        changes: { from, to, insert: '' },
        selection: { anchor: from },
    });

    const insert =
        typeof command.insert === 'function'
            ? await command.insert(view)
            : command.insert;

    if (!insert) {
        return;
    }

    const position = view.state.selection.main.head;
    const line = view.state.doc.lineAt(position);
    const before = view.state.sliceDoc(line.from, position);
    // A block typed on an empty list item or quote replaces its marker, as in Notion.
    const replaceMarker = insert.block === true && EMPTY_MARKER.test(before);
    const start = replaceMarker ? line.from : position;
    const prefix =
        insert.block && !replaceMarker && before.trim() !== '' ? '\n' : '';
    const text = prefix + insert.text;

    view.dispatch({
        changes: { from: start, to: position, insert: text },
        selection: EditorSelection.cursor(
            start + prefix.length + (insert.cursor ?? insert.text.length),
        ),
        scrollIntoView: true,
    });
    view.focus();
}

/**
 * The icon tile drawn beside each slash command in the menu.
 */
export const slashIcon = {
    position: 20,
    render: (completion: Completion): HTMLElement | null => {
        const icon = (completion as SlashCompletion).kiteIcon;

        if (icon === undefined) {
            return null;
        }

        const element = document.createElement('span');
        element.className = 'cm-kite-slash-icon';
        element.textContent = icon;

        return element;
    },
};

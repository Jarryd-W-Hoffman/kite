/**
 * Kite vault watcher.
 *
 * Runs as a NativePHP child process (Electron's bundled Node) and watches a
 * vault folder with native OS file events. Changes are debounced and written
 * to stdout as one JSON line per batch, which NativePHP forwards to Laravel.
 *
 * Usage: node vault-watcher.mjs <vault-path> [comma-separated-extensions]
 */
import { watch } from 'node:fs';
import { extname, sep } from 'node:path';
import { exit } from 'node:process';

const DEBOUNCE_MS = 150;

const root = process.argv[2];
const extensions = new Set(
    (process.argv[3] ?? 'md')
        .split(',')
        .map((extension) => extension.trim().toLowerCase())
        .filter(Boolean),
);

if (!root) {
    process.stderr.write(
        'Usage: vault-watcher.mjs <vault-path> [extensions]\n',
    );
    exit(64);
}

const pending = new Set();
let timer = null;

const isIgnored = (relativePath) =>
    relativePath
        .split(sep)
        .some(
            (segment) => segment.startsWith('.') || segment === 'node_modules',
        );

// Paths without an extension may be folders being created, renamed or removed.
const isRelevant = (relativePath) => {
    const extension = extname(relativePath).slice(1).toLowerCase();

    return extension === '' || extensions.has(extension);
};

const flush = () => {
    timer = null;

    const paths = [...pending];
    pending.clear();

    process.stdout.write(`${JSON.stringify({ root, paths })}\n`);
};

const schedule = (relativePath) => {
    pending.add(relativePath);

    if (timer === null) {
        timer = setTimeout(flush, DEBOUNCE_MS);
    }
};

// Recursive watching fails silently on Linux when inotify watches run out, so
// probe the root first and fail loudly instead.
try {
    watch(root).close();
} catch (error) {
    process.stderr.write(`${String(error)}\n`);
    exit(1);
}

const watcher = watch(root, { recursive: true }, (_event, filename) => {
    if (!filename) {
        schedule('*');

        return;
    }

    const relativePath = filename.toString();

    if (!isIgnored(relativePath) && isRelevant(relativePath)) {
        schedule(relativePath.split(sep).join('/'));
    }
});

watcher.on('error', (error) => {
    process.stderr.write(`${String(error)}\n`);
    exit(1);
});

process.stdout.write(`${JSON.stringify({ root, ready: true })}\n`);

const shutdown = () => {
    watcher.close();
    exit(0);
};

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

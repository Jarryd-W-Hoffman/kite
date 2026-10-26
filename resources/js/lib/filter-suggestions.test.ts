import { describe, expect, test } from 'vite-plus/test';
import {
    MAX_SUGGESTIONS,
    folderSuggestions,
    rankFolders,
    typedFolderFilter,
} from '@/lib/filter-suggestions';

const folders = [
    'Archive',
    'Archive/Projects',
    'Projects',
    'Projects/shell-tools',
    'Projects/Website',
    'Work/Client A',
];

describe('typedFolderFilter', () => {
    test('finds the folder value around the cursor', () => {
        expect(typedFolderFilter('folder:Proj', 11)).toEqual({
            value: 'Proj',
            start: 7,
            end: 11,
        });
        expect(typedFolderFilter('tag:x folder:Pro -status:done', 15)).toEqual({
            value: 'Pro',
            start: 13,
            end: 16,
        });
        expect(typedFolderFilter('-FOLDER:', 8)).toEqual({
            value: '',
            start: 8,
            end: 8,
        });
    });

    test('reads quoted values with spaces', () => {
        expect(typedFolderFilter('folder:"Work/Cli', 16)).toEqual({
            value: 'Work/Cli',
            start: 7,
            end: 16,
        });
        expect(typedFolderFilter('folder:"Work/Client A" tag:x', 12)).toEqual({
            value: 'Work/Client A',
            start: 7,
            end: 22,
        });
    });

    test('ignores other filters, words, and the cursor before the value', () => {
        expect(typedFolderFilter('tag:proj', 8)).toBeNull();
        expect(typedFolderFilter('folders', 7)).toBeNull();
        expect(typedFolderFilter('budget folder:Work', 6)).toBeNull();
        expect(typedFolderFilter('folder:Work', 3)).toBeNull();
        expect(typedFolderFilter('', 0)).toBeNull();
    });
});

describe('rankFolders', () => {
    test('offers any folder whose path contains the text, names first', () => {
        expect(rankFolders(folders, 'shell')).toEqual(['Projects/shell-tools']);
        expect(rankFolders(folders, 'proj')).toEqual([
            'Projects',
            'Archive/Projects',
            'Projects/shell-tools',
            'Projects/Website',
        ]);
        expect(rankFolders(folders, 'TOOLS')).toEqual(['Projects/shell-tools']);
    });

    test('starts with the top folders when nothing is typed yet', () => {
        expect(rankFolders(folders, '').slice(0, 3)).toEqual([
            'Archive',
            'Projects',
            'Archive/Projects',
        ]);
    });

    test('leaves out a folder already typed in full, but not those inside it', () => {
        expect(rankFolders(folders, 'projects/')).toEqual([
            'Projects/shell-tools',
            'Projects/Website',
        ]);
        expect(rankFolders(folders, 'Projects/Website')).toEqual([]);
    });

    test('shows a limited number', () => {
        const many = Array.from(
            { length: 20 },
            (_, index) => `Folder ${index}`,
        );

        expect(rankFolders(many, 'folder')).toHaveLength(MAX_SUGGESTIONS);
        expect(rankFolders(many, 'folder')[2]).toBe('Folder 2');
    });
});

describe('folderSuggestions', () => {
    test('replaces the typed value with the whole path', () => {
        expect(folderSuggestions('folder:shell -tag:x', 12, folders)).toEqual([
            {
                label: 'Projects/shell-tools',
                query: 'folder:Projects/shell-tools -tag:x',
                cursor: 27,
            },
        ]);
    });

    test('quotes paths with spaces', () => {
        expect(folderSuggestions('tag:x folder:cli', 16, folders)).toEqual([
            {
                label: 'Work/Client A',
                query: 'tag:x folder:"Work/Client A"',
                cursor: 28,
            },
        ]);
    });

    test('suggests nothing outside a folder filter', () => {
        expect(folderSuggestions('tag:proj', 8, folders)).toEqual([]);
    });
});

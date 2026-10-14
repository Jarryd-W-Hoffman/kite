import { EditorState } from '@codemirror/state';
import type { Extension } from '@codemirror/state';
import { EditorView } from '@codemirror/view';

/**
 * Typewriter scrolling: whenever the writer types or moves the cursor, the line they're on is
 * scrolled to the middle of the editor, so their eyes stay in one place. Padding above and below
 * the text lets the first and last lines reach the middle too.
 */
export const typewriterScrolling: Extension = [
    EditorState.transactionExtender.of((transaction) =>
        transaction.selection &&
        (transaction.isUserEvent('input') ||
            transaction.isUserEvent('delete') ||
            transaction.isUserEvent('select') ||
            transaction.isUserEvent('move') ||
            transaction.isUserEvent('undo') ||
            transaction.isUserEvent('redo'))
            ? {
                  effects: EditorView.scrollIntoView(
                      transaction.selection.main.head,
                      { y: 'center' },
                  ),
              }
            : null,
    ),
    EditorView.theme({
        '.cm-content': { paddingTop: '40vh', paddingBottom: '50vh' },
    }),
    EditorView.editorAttributes.of({ 'data-typewriter': 'true' }),
];

import { useMemo } from 'react';
import { requestJson } from '@/lib/kite-http';
import { BASE_COMMANDS } from '@/lib/slash-commands';
import type { SlashCommand } from '@/lib/slash-commands';
import { show as showTemplate } from '@/routes/templates';
import type { NoteTemplate, VaultSummary } from '@/types';

/**
 * The editor's slash commands: the built-in ones, then one for each template, filled in for
 * the note it's inserted into.
 */
export function useNoteSlashCommands(
    vault: VaultSummary,
    templates: NoteTemplate[],
    title: string,
    path: string,
): SlashCommand[] {
    return useMemo<SlashCommand[]>(
        () => [
            ...BASE_COMMANDS,
            ...templates.map((template): SlashCommand => ({
                id: `template-${template.id}`,
                label: template.name,
                section: 'Templates',
                description: 'Insert this template',
                keywords: 'template',
                icon: '⧉',
                insert: async () => {
                    const { contents } = await requestJson<{
                        contents: string;
                    }>(
                        showTemplate(
                            { vault: vault.id, path: template.path },
                            {
                                query: {
                                    title,
                                    folder: path.includes('/')
                                        ? path.slice(0, path.lastIndexOf('/'))
                                        : '',
                                },
                            },
                        ),
                    );

                    return { text: contents, block: true };
                },
            })),
        ],
        [templates, vault.id, title, path],
    );
}

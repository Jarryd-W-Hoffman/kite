<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;

/**
 * Gives each task's checkbox a name for screen readers, "Done" or "To do", as the editor's do.
 * The task's text follows the checkbox, so it reads as e.g. "Done, checkbox, checked: Buy milk".
 */
final readonly class TaskCheckboxLabeller
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof TaskListItemMarker) {
                $node->data->set('attributes/aria-label', $node->isChecked() ? 'Done' : 'To do');
            }
        }
    }
}

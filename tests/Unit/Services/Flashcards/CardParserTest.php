<?php

declare(strict_types=1);

use App\Services\Flashcards\Card;
use App\Services\Flashcards\CardParser;

function cards(string $markdown): array
{
    return array_map(fn (Card $card): array => [$card->kind, $card->front, $card->back, $card->line], new CardParser()->parse($markdown));
}

it('reads one-line cards, list items included', function (): void {
    expect(cards("Capital of France :: Paris\n- What is 2+2?::4\n1. [ ] Ignored task :: no, a card"))->toBe([
        ['basic', 'Capital of France', 'Paris', 1],
        ['basic', 'What is 2+2?', '4', 2],
        ['basic', 'Ignored task', 'no, a card', 3],
    ]);
});

it('reads reversible cards as a card each way', function (): void {
    expect(cards('perro ::: dog'))->toBe([
        ['reversed', 'perro', 'dog', 1],
        ['reversed', 'dog', 'perro', 1],
    ]);
});

it('reads multi-line cards split by a question mark line', function (): void {
    expect(cards("Intro paragraph.\n\nName the layers\nof the OSI model\n?\nPhysical, Data link,\nNetwork…\n\n?\nLone mark"))->toBe([
        ['multiline', "Name the layers\nof the OSI model", "Physical, Data link,\nNetwork…", 3],
    ]);
});

it('makes a cloze card for each highlight', function (): void {
    expect(cards('The ==mitochondria== is the ==powerhouse== of the cell'))->toBe([
        ['cloze', 'The […] is the powerhouse of the cell', 'The **mitochondria** is the powerhouse of the cell', 1],
        ['cloze', 'The mitochondria is the […] of the cell', 'The mitochondria is the **powerhouse** of the cell', 1],
    ]);
});

it('never reads code as cards', function (): void {
    expect(cards("Call `Foo::bar()` here\n```php\nFoo::bar() :: nope\n== nope ==\n```\nUse `==` to compare ==loosely==\n~~~\na :: b\n~~~"))->toBe([
        ['cloze', 'Use `==` to compare […]', 'Use `==` to compare **loosely**', 6],
    ]);
});

it('ignores lines that only look like cards', function (): void {
    expect(cards(":: no front\nno back ::\n== spaced ==\nplain text"))->toBe([]);
});

it('numbers lines from where the note body starts', function (): void {
    expect(new CardParser()->parse('Q :: A', 5)[0]->line)->toBe(5);
});

it('keys a card by its question alone', function (): void {
    $card = new Card('basic', 'Capital  of France', 'Paris', 1);

    expect($card->key())->toMatch('/^[0-9a-f]{20}$/')
        ->toBe(new Card('basic', 'Capital of France', 'Lyon', 9)->key())
        ->not->toBe(new Card('reversed', 'Capital of France', 'Paris', 1)->key())
        ->not->toBe(new Card('basic', 'Capital of Spain', 'Paris', 1)->key());
});

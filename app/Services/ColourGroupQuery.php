<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Database\Query\Builder;
use InvalidArgumentException;

/**
 * A parsed colour group query, e.g. `tag:project folder:Work -tag:archived category:person`.
 *
 * Space-separated terms must all match; a leading `-` negates a term. `tag:` matches the tag and
 * any nested tag below it, `folder:` the folder and everything inside it, `path:` and `title:` (or
 * a bare word) match text anywhere in the path or title, and any other `field:value` compares a
 * frontmatter field (`field:*` only requires the field to exist). Matching ignores case.
 *
 * A query can match one note's data in PHP (`matches()`) or narrow a query on the `notes` table
 * (`applyTo()`), so a large vault is filtered by the database. Both give the same answer.
 */
final readonly class ColourGroupQuery
{
    private const string TERM = '/(-?)(?:([\p{L}_][\p{L}\p{N}_-]*):)?(?:"([^"]*)"|(\S+))/u';

    /**
     * @param  list<array{field: string, value: string, negated: bool}>  $terms
     */
    private function __construct(private array $terms)
    {
        //
    }

    /**
     * @throws InvalidArgumentException when the query is empty or a term has no value
     */
    public static function parse(string $query): self
    {
        preg_match_all(self::TERM, $query, $matches, PREG_SET_ORDER);

        $terms = [];

        foreach ($matches as $match) {
            $field = mb_strtolower($match[2] ?? '');
            $value = mb_trim(($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));

            // A bare `field:` falls through to a plain word ending in a colon; treat it as a missing value.
            $missingValue = $value === '' || (($match[2] ?? '') === '' && preg_match('/^[\p{L}_][\p{L}\p{N}_-]*:$/u', $value) === 1);

            throw_if($missingValue, InvalidArgumentException::class, 'Every term in a colour group query needs a value.');

            $terms[] = [
                'field' => $field === '' ? 'title' : $field,
                'value' => mb_strtolower($value),
                'negated' => $match[1] === '-',
            ];
        }

        throw_if($terms === [], InvalidArgumentException::class, 'A colour group query cannot be empty.');

        return new self($terms);
    }

    /**
     * @param  array{title: string, path: string, tags: list<string>, frontmatter: array<mixed>}  $note
     */
    public function matches(array $note): bool
    {
        return array_all($this->terms, fn (array $term): bool => $this->matchesTerm($term['field'], $term['value'], $note) !== $term['negated']);
    }

    /**
     * Narrow a query on the `notes` table to the notes this query matches. Text is compared with
     * `kite_lower()`, the same Unicode lowercasing `matches()` uses.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $notes
     * @return TBuilder
     */
    public function applyTo(Builder $notes): Builder
    {
        foreach ($this->terms as ['field' => $field, 'value' => $value, 'negated' => $negated]) {
            $constraint = fn (Builder $query): Builder => $this->constrain($query, $field, $value);

            $negated ? $notes->whereNot($constraint) : $notes->where($constraint);
        }

        return $notes;
    }

    private function constrain(Builder $query, string $field, string $value): Builder
    {
        return match ($field) {
            'tag' => $query->whereExists(fn (Builder $tags): Builder => $this->constrainTag(
                $tags->selectRaw('1')->from('note_tags')->whereColumn('note_tags.note_id', 'notes.id'),
                mb_ltrim($value, '#'),
            )),
            'folder' => $query->whereRaw('substr(kite_lower(notes.path), 1, ?) = ?', [mb_strlen(mb_trim($value, '/')) + 1, mb_trim($value, '/').'/']),
            'path', 'title' => $query->whereRaw("instr(kite_lower(notes.{$field}), ?) > 0", [$value]),
            default => $this->constrainField($query, $field, $value),
        };
    }

    private function constrainTag(Builder $tags, string $tag): Builder
    {
        return $tags->where(fn (Builder $names): Builder => $names
            ->where('note_tags.name', $tag)
            ->orWhereRaw('substr(note_tags.name, 1, ?) = ?', [mb_strlen($tag) + 1, $tag.'/']));
    }

    /**
     * A frontmatter field whose value, or an item of whose list, equals the value. Booleans read
     * as `true`/`false`; nested maps never match.
     */
    private function constrainField(Builder $query, string $field, string $value): Builder
    {
        $scalar = fn (string $item): string => "({$item}.type IN ('text', 'integer', 'real') AND kite_lower({$item}.value) = ?) OR ({$item}.type IN ('true', 'false') AND {$item}.type = ?)";
        $bindings = $value === '*' ? [$field] : [$field, $value, $value, $value, $value];

        return $query->whereRaw(
            'EXISTS (SELECT 1 FROM json_each(notes.frontmatter) AS field WHERE kite_lower(field.key) = ?'
            .($value === '*' ? '' : ' AND ('.$scalar('field')." OR (field.type = 'array' AND EXISTS (SELECT 1 FROM json_each(field.value) AS item WHERE ".$scalar('item').')))')
            .')',
            $bindings,
        );
    }

    /**
     * @param  array{title: string, path: string, tags: list<string>, frontmatter: array<mixed>}  $note
     */
    private function matchesTerm(string $field, string $value, array $note): bool
    {
        return match ($field) {
            'tag' => $this->matchesTag(mb_ltrim($value, '#'), $note['tags']),
            'folder' => $this->isWithin(mb_trim($value, '/'), mb_strtolower($note['path'])),
            'path' => str_contains(mb_strtolower($note['path']), $value),
            'title' => str_contains(mb_strtolower($note['title']), $value),
            default => $this->matchesField($field, $value, $note['frontmatter']),
        };
    }

    /**
     * @param  list<string>  $tags
     */
    private function matchesTag(string $tag, array $tags): bool
    {
        return array_any($tags, fn (string $candidate): bool => $candidate === $tag || str_starts_with($candidate, $tag.'/'));
    }

    private function isWithin(string $folder, string $path): bool
    {
        return str_starts_with($path, $folder.'/');
    }

    /**
     * @param  array<mixed>  $frontmatter
     */
    private function matchesField(string $field, string $value, array $frontmatter): bool
    {
        foreach ($frontmatter as $key => $fieldValue) {
            if (mb_strtolower((string) $key) !== $field) {
                continue;
            }

            if ($value === '*') {
                return true;
            }

            // Lists match any item; nested maps are not compared.
            foreach (is_array($fieldValue) ? (array_is_list($fieldValue) ? $fieldValue : []) : [$fieldValue] as $item) {
                if (is_scalar($item) && mb_strtolower(match ($item) {
                    true => 'true',
                    false => 'false',
                    default => (string) $item,
                }) === $value) {
                    return true;
                }
            }
        }

        return false;
    }
}

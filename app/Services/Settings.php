<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Native\Desktop\Enums\SystemThemesEnum;

/**
 * User preferences stored in the database. Settings backed by config are applied over it at boot,
 * so every process (web, queue worker, watcher listeners) sees the same values.
 */
final readonly class Settings
{
    /**
     * @return array<string, array{config: string|null, default: mixed, rules: list<mixed>}>
     */
    public function definitions(): array
    {
        return [
            'appearance' => ['config' => null, 'default' => 'system', 'rules' => ['required', Rule::enum(SystemThemesEnum::class)]],
            'editor_font' => ['config' => null, 'default' => 'mono', 'rules' => ['required', Rule::in(['sans', 'serif', 'mono'])]],
            'editor_font_size' => ['config' => null, 'default' => 14, 'rules' => ['required', 'integer', 'between:12,22']],
            'editor_width' => ['config' => null, 'default' => 'normal', 'rules' => ['required', Rule::in(['narrow', 'normal', 'wide', 'full'])]],
            'autosave_ms' => ['config' => null, 'default' => 400, 'rules' => ['required', 'integer', 'between:200,10000']],
            'ignored_folders' => ['config' => 'kite.ignored_folders', 'default' => ['node_modules'], 'rules' => ['present', 'array', 'max:50']],
            'ignored_folders.*' => ['config' => null, 'default' => null, 'rules' => ['string', 'max:200', 'not_regex:/(^|\/)\.\.(\/|$)/']],
            'attachments_folder' => ['config' => 'kite.attachments.folder', 'default' => 'attachments', 'rules' => ['nullable', 'string', 'max:200', 'not_regex:/(^|\/)\.\.(\/|$)/']],
            'daily_folder' => ['config' => 'kite.daily.folder', 'default' => 'Daily', 'rules' => ['nullable', 'string', 'max:200', 'not_regex:/(^|\/)\.\.(\/|$)/']],
            'daily_format' => ['config' => 'kite.daily.format', 'default' => 'Y-m-d', 'rules' => ['required', 'string', 'max:50', 'regex:/^[dDjlNSwzWFmMntLoYyaABgGhHisuv\-_. ]+$/']],
            'daily_template' => ['config' => 'kite.daily.template', 'default' => null, 'rules' => ['nullable', 'string', 'max:200']],
            'templates_folder' => ['config' => 'kite.templates.folder', 'default' => 'Templates', 'rules' => ['nullable', 'string', 'max:200', 'not_regex:/(^|\/)\.\.(\/|$)/']],
            'publish_folder' => ['config' => 'kite.publish.folder', 'default' => null, 'rules' => ['nullable', 'string', 'max:1024', 'regex:/^(\/|[A-Za-z]:[\\\/])/', 'not_regex:/(^|[\\\/])\.\.([\\\/]|$)/']],
            'tree_group_colours' => ['config' => null, 'default' => true, 'rules' => ['required', 'boolean']],
            'capture_shortcut' => ['config' => 'kite.capture.shortcut', 'default' => 'CmdOrCtrl+Shift+Space', 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9+ ]*$/']],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return array_map(fn (array $definition): array => $definition['rules'], $this->definitions());
    }

    /**
     * Current values: stored settings, falling back to config, then to built-in defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = $this->stored();
        $values = [];

        foreach ($this->definitions() as $key => $definition) {
            if (str_contains($key, '.')) {
                continue;
            }

            $values[$key] = array_key_exists($key, $stored)
                ? $stored[$key]
                : ($definition['config'] === null ? $definition['default'] : config($definition['config'], $definition['default']));
        }

        return $values;
    }

    /**
     * Persist validated settings and apply them to the running config.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        $definitions = $this->definitions();

        foreach ($values as $key => $value) {
            if (! isset($definitions[$key]) || str_contains($key, '.')) {
                continue;
            }

            if (is_string($value)) {
                $value = mb_trim($value);
            }

            DB::table('settings')->upsert(['key' => $key, 'value' => json_encode($value)], ['key'], ['value']);
        }

        $this->applyToConfig();
    }

    /**
     * Override config with stored settings. Safe to call before the settings table exists.
     */
    public function applyToConfig(): void
    {
        foreach ($this->stored() as $key => $value) {
            $configKey = $this->definitions()[$key]['config'] ?? null;

            if ($configKey !== null) {
                config([$configKey => $value]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        try {
            $rows = DB::table('settings')->get(['key', 'value']);
        } catch (QueryException) {
            return [];
        }

        $stored = [];

        foreach ($rows as $row) {
            $stored[Row::string($row, 'key')] = json_decode(Row::string($row, 'value'), true);
        }

        return $stored;
    }
}

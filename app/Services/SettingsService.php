<?php

namespace App\Services;

use App\Domain\Terms;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->cache === null) {
            $defaults = config('mihak.defaults');
            $stored = Schema::hasTable('settings') ? Setting::query()->pluck('value', 'key')->all() : [];
            $values = [];
            foreach ($defaults as $key => $default) {
                $raw = $stored[$key] ?? null;
                $values[$key] = match (true) {
                    $raw === null => $default,
                    is_bool($default) => $raw === '1',
                    is_string($default) => (string) $raw,
                    default => (int) $raw,
                };
            }
            $this->cache = $values;
        }

        return $this->cache;
    }

    public function int(string $key): int
    {
        return (int) ($this->all()[$key] ?? 0);
    }

    public function bool(string $key): bool
    {
        return (bool) ($this->all()[$key] ?? false);
    }

    public function string(string $key): string
    {
        return (string) ($this->all()[$key] ?? '');
    }

    /** @param array<string, int|bool|string> $values */
    public function put(array $values): void
    {
        $defaults = config('mihak.defaults');
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $defaults)) {
                continue;
            }
            $stored = match (true) {
                is_bool($defaults[$key]) => $value ? '1' : '0',
                is_string($defaults[$key]) => (string) $value,
                default => (string) (int) $value,
            };
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
        }
        $this->cache = null;
    }

    /** الخط المختار مع رابط تحميله من Google Fonts */
    public function font(): array
    {
        $fonts = config('mihak.fonts');
        $name = $this->string('site_font');
        if (! isset($fonts[$name])) {
            $name = array_key_first($fonts);
        }

        return ['name' => $name, 'url' => self::fontUrl([$name])];
    }

    /** @param array<int, string> $names */
    public static function fontUrl(array $names): string
    {
        $fonts = config('mihak.fonts');
        $families = array_map(fn (string $n) => 'family='.$fonts[$n]['google'], array_filter($names, fn ($n) => isset($fonts[$n])));

        return 'https://fonts.googleapis.com/css2?'.implode('&', $families).'&display=swap';
    }

    /** الشروط المالية لحملة جديدة حسب الإعدادات الحالية */
    public function terms(): Terms
    {
        return Terms::fromSettings($this->all());
    }
}

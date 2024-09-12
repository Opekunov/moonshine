<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Traits;

use Closure;
use MoonShine\Contracts\Core\RenderableContract;

trait WithComponentsPusher
{
    /**
     * @var array<string, Closure|RenderableContract>
     */
    private static array $pushedComponents = [];

    public static function pushComponent(Closure|RenderableContract $component): void
    {
        static::$pushedComponents[] = $component;
    }

    protected function getPushedComponents(): array
    {
        return collect(static::$pushedComponents)
            ->map(fn (Closure|RenderableContract $component) => $component instanceof Closure
                ? value($component, $this)
                : $component
            )
            ->toArray();
    }
}
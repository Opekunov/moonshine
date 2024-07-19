<?php

declare(strict_types=1);

namespace MoonShine\UI\Traits;

use Closure;
use MoonShine\Support\Components\MoonShineComponentAttributeBag;

trait Removable
{
    protected bool $removable = false;

    protected array $removableAttributes = [];

    public function removable(
        Closure|bool|null $condition = null,
        array $attributes = []
    ): static {
        $this->removable = value($condition, $this) ?? true;
        $this->removableAttributes = $attributes;

        return $this;
    }

    public function getRemovableAttributes(): MoonShineComponentAttributeBag
    {
        return new MoonShineComponentAttributeBag($this->removableAttributes);
    }

    public function getHiddenAttributes(): MoonShineComponentAttributeBag
    {
        return $this->getAttributes()->only(['data-level'])->merge([
            'name' => $this->getHiddenRemainingValuesKey(),
            'data-name' => $this->getHiddenRemainingValuesName(),
        ]);
    }

    public function isRemovable(): bool
    {
        return $this->removable;
    }
}
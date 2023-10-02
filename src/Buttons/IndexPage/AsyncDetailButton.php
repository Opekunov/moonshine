<?php

declare(strict_types=1);

namespace MoonShine\Buttons\IndexPage;

use MoonShine\ActionButtons\ActionButton;
use MoonShine\Pages\Crud\DetailPage;
use MoonShine\Resources\ModelResource;

class AsyncDetailButton
{
    public static function for(ModelResource $resource): ActionButton
    {
        return ActionButton::make(
            '',
            url: static fn ($data): string => to_page(
                page: DetailPage::class,
                resource: $resource,
                params: ['resourceItem' => $data->getKey()],
                fragment: 'crud-show-table'
            )
        )
            ->icon('heroicons.outline.eye')
            ->inModal(
                fn (): array|string|null => __('moonshine::ui.create'),
                fn (): string => '',
                async: true
            )
            ->showInLine();
    }
}

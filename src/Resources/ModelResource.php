<?php

declare(strict_types=1);

namespace MoonShine\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\View\ComponentAttributeBag;
use MoonShine\Enums\ClickAction;
use MoonShine\Enums\JsEvent;
use MoonShine\Exceptions\ResourceException;
use MoonShine\Fields\Field;
use MoonShine\Fields\Fields;
use MoonShine\Fields\Relationships\ModelRelationField;
use MoonShine\Pages\Crud\DetailPage;
use MoonShine\Pages\Crud\FormPage;
use MoonShine\Pages\Crud\IndexPage;
use MoonShine\Pages\Page;
use MoonShine\Support\AlpineJs;
use MoonShine\Traits\Resource\ResourceModelActions;
use MoonShine\Traits\Resource\ResourceModelCrudRouter;
use MoonShine\Traits\Resource\ResourceModelEvents;
use MoonShine\Traits\Resource\ResourceModelPolicy;
use MoonShine\Traits\Resource\ResourceModelQuery;
use MoonShine\Traits\Resource\ResourceModelValidation;
use MoonShine\Traits\Resource\ResourceWithButtons;
use MoonShine\Traits\Resource\ResourceWithFields;
use MoonShine\Traits\WithIsNowOnRoute;
use MoonShine\TypeCasts\ModelCast;
use Throwable;

/**
 * @template TModel of Model
 */
abstract class ModelResource extends Resource
{
    use ResourceWithFields;
    use ResourceWithButtons;
    /** @use ResourceModelValidation<TModel> */
    use ResourceModelValidation;
    use ResourceModelActions;
    use ResourceModelPolicy;
    /** @use ResourceModelQuery<TModel> */
    use ResourceModelQuery;
    /** @use ResourceModelCrudRouter<TModel> */
    use ResourceModelCrudRouter;
    /** @use ResourceModelEvents<TModel> */
    use ResourceModelEvents;
    use WithIsNowOnRoute;

    protected string $model;

    protected string $column = 'id';

    protected bool $createInModal = false;

    protected bool $editInModal = false;

    protected bool $detailInModal = false;

    protected bool $isAsync = false;

    protected bool $isPrecognitive = false;

    protected bool $deleteRelationships = false;

    /**
     * The click action to use when clicking on the resource in the table.
     */
    protected ?ClickAction $clickAction = null;

    public function flushState(): void
    {
        $this->item = null;
        $this->query = null;
        $this->pages = null;
    }

    protected function pages(): array
    {
        return [
            IndexPage::make($this->title()),
            FormPage::make(
                $this->getItemID()
                    ? __('moonshine::ui.edit')
                    : __('moonshine::ui.add')
            ),
            DetailPage::make(__('moonshine::ui.show')),
        ];
    }

    public function indexPage(): ?Page
    {
        return $this->getPages()->indexPage();
    }

    public function formPage(): ?Page
    {
        return $this->getPages()->formPage();
    }

    public function detailPage(): ?Page
    {
        return $this->getPages()->detailPage();
    }

    /**
     * @return TModel
     */
    public function getModel(): Model
    {
        return new $this->model();
    }

    public function getModelCast(): ModelCast
    {
        return ModelCast::make($this->model);
    }

    public function column(): string
    {
        return $this->column;
    }

    public function isCreateInModal(): bool
    {
        return $this->createInModal;
    }

    public function isEditInModal(): bool
    {
        return $this->editInModal;
    }

    public function isDetailInModal(): bool
    {
        return $this->detailInModal;
    }

    public function isAsync(): bool
    {
        return $this->isAsync;
    }

    public function isPrecognitive(): bool
    {
        return $this->isPrecognitive;
    }

    public function deleteRelationships(): bool
    {
        return $this->deleteRelationships;
    }

    public function getClickAction(): ?string
    {
        return $this->clickAction?->value;
    }

    public function metrics(): array
    {
        return [];
    }

    public function trAttributes(): Closure
    {
        return fn (mixed $data, int $row, ComponentAttributeBag $attr): ComponentAttributeBag => $attr;
    }

    public function tdAttributes(): Closure
    {
        return fn (mixed $data, int $row, int $cell, ComponentAttributeBag $attr): ComponentAttributeBag => $attr;
    }

    public function search(): array
    {
        return ['id'];
    }

    public function listComponentName(): string
    {
        return rescue(
            fn (): string => $this->indexPage()?->listComponentName(),
            fn (): string => 'index-table',
            false
        );
    }

    public function listEventName(?string $name = null): string
    {
        $name ??= $this->listComponentName();

        return rescue(
            fn (): string => AlpineJs::event($this->indexPage()?->listEventName() ?? '', $name),
            fn (): string => AlpineJs::event(JsEvent::TABLE_UPDATED, $name),
            false
        );
    }

    /**
     * @param  array<int|string>  $ids
     */
    public function massDelete(array $ids): void
    {
        $this->beforeMassDeleting($ids);

        $this->getModel()
            ->newModelQuery()
            ->whereIn($this->getModel()->getKeyName(), $ids)
            ->get()
            ->each(fn (Model $item): ?bool => $item->delete());

        $this->afterMassDeleted($ids);
    }

    /**
     * @param TModel $item
     * @param Fields|null $fields
     *
     * @throws Throwable
     */
    public function delete(Model $item, ?Fields $fields = null): bool
    {
        $item = $this->beforeDeleting($item);

        $fields ??= $this->getFormFields()->onlyFields();

        $fields->fill($item->toArray(), $item);

        $fields->each(fn (Field $field): mixed => $field->afterDestroy($item));

        if ($this->deleteRelationships()) {
            $this->getOutsideFields()->each(function (ModelRelationField $field) use ($item): void {
                $relationItems = $item->{$field->getRelationName()};

                ! $field->toOne() ?: $relationItems = collect([$relationItems]);

                $relationItems->each(
                    fn ($relationItem): mixed => $field->resolveFill($relationItem->toArray())
                        ->afterDestroy($relationItem)
                );
            });
        }

        return tap($item->delete(), fn (): Model => $this->afterDeleted($item));
    }

    public function onSave(Field $field): Closure
    {
        return static function (Model $item) use ($field): Model {
            if(! $field->hasRequestValue() && ! $field->defaultIfExists()) {
                return $item;
            }

            $value = $field->requestValue() !== false ? $field->requestValue() : null;

            data_set($item, $field->column(), $value);

            return $item;
        };
    }

    /**
     * @param TModel $item
     * @param Fields|null $fields
     *
     * @return TModel
     * @throws ResourceException
     * @throws Throwable
     */
    public function save(Model $item, ?Fields $fields = null): Model
    {
        $fields ??= $this->getFormFields()->onlyFields();

        $fields->fill($item->toArray(), $item);

        try {
            $fields->each(fn (Field $field): mixed => $field->beforeApply($item));

            if (! $item->exists) {
                $item = $this->beforeCreating($item);
            }

            if ($item->exists) {
                $item = $this->beforeUpdating($item);
            }

            $fields->withoutOutside()
                ->each(fn (Field $field): mixed => $field->apply($this->onSave($field), $item));

            if ($item->save()) {
                $wasRecentlyCreated = $item->wasRecentlyCreated;

                $fields->each(fn (Field $field): mixed => $field->afterApply($item));

                $item->save();

                if ($wasRecentlyCreated) {
                    $item = $this->afterCreated($item);
                }

                if (! $wasRecentlyCreated) {
                    $item = $this->afterUpdated($item);
                }
            }
        } catch (QueryException $queryException) {
            throw new ResourceException($queryException->getMessage());
        }

        $this->setItem($item);

        return $item;
    }
}

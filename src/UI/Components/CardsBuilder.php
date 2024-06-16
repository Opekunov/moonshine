<?php

declare(strict_types=1);

namespace MoonShine\UI\Components;

use Closure;
use Illuminate\Support\Collection;
use MoonShine\Support\Traits\HasAsync;
use MoonShine\UI\Collections\Fields;
use MoonShine\UI\Contracts\Collections\FieldsCollection;
use MoonShine\UI\Fields\Field;
use MoonShine\UI\Traits\Components\WithColumnSpan;
use Throwable;

/**
 * @method static static make(iterable $items = [], Fields|array $fields = [])
 */
final class CardsBuilder extends IterableComponent
{
    use HasAsync;
    use WithColumnSpan;

    protected string $view = 'moonshine::components.cards';

    protected array $components = [];

    protected Closure|string $title = '';

    protected Closure|array|string $subtitle = '';

    protected Closure|string $thumbnail = '';

    protected Closure|string $url = '';

    protected Closure|string $content = '';

    protected Closure|string $header = '';

    protected bool $overlay = false;

    protected ?Closure $customComponent = null;

    protected array|Closure $componentAttributes = [];

    public function __construct(
        iterable $items = [],
        Fields|array $fields = [],
    ) {
        parent::__construct();

        $this->items($items);
        $this->fields($fields);
        $this->columnSpan(4);

        $this->withAttributes([]);
    }

    public function title(Closure|string $value): self
    {
        $this->title = $value;

        return $this;
    }

    public function subtitle(Closure|string $value): self
    {
        $this->subtitle = $value;

        return $this;
    }

    public function thumbnail(Closure|string $value): self
    {
        $this->thumbnail = $value;

        return $this;
    }

    public function url(Closure|string $value): self
    {
        $this->url = $value;

        return $this;
    }

    public function overlay(): self
    {
        $this->overlay = true;

        return $this;
    }

    public function content(Closure|string $value): self
    {
        $this->content = $value;

        return $this;
    }

    public function header(Closure|string $value): self
    {
        $this->header = $value;

        return $this;
    }

    protected function prepareAsyncUrl(Closure|string|null $url = null): Closure|string|null
    {
        return $url ?? fn (): string => moonshineRouter()->getEndpoints()->asyncComponent(name: $this->getName());
    }

    public function componentAttributes(array|Closure $attributes): self
    {
        $this->componentAttributes = $attributes;

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function getComponents(): Collection
    {
        $fields = $this->preparedFields();

        return $this->getItems()->map(function (mixed $data, int $index) use ($fields) {
            $casted = $this->castData($data);

            $fields = $this->getFilledFields($casted->toArray(), $casted, $index, $fields);

            if(! is_null($this->customComponent)) {
                return value($this->customComponent, $data, $index, $this);
            }

            $buttons = $this->getButtons($casted);

            return Card::make(...$this->getMapper($data, $fields, $index))
                ->content((string) value($this->content, $data, $index, $this))
                ->header((string) value($this->header, $data, $index, $this))
                ->customAttributes(value($this->componentAttributes, $data, $index, $this))
                ->when(
                    $buttons->isNotEmpty(),
                    fn (Card $card): Card => $card->actions(
                        fn () => ActionGroup::make($buttons->toArray())
                    )
                );
        });
    }

    public function customComponent(Closure $component): self
    {
        $this->customComponent = $component;

        return $this;
    }

    protected function getMapperValue(string $column, mixed $data, int $index): string|array
    {
        return is_string($this->{$column})
            ? data_get($data, $this->{$column}, '')
            : value($this->{$column}, $data, $index, $this);
    }

    protected function getMapper(mixed $data, FieldsCollection $fields, int $index): array
    {
        $values = $fields->values()
            ->mapWithKeys(fn (Field $value): array => [$value->getLabel() => (string) $value->preview()])
            ->toArray();

        return [
            'title' => $this->getMapperValue('title', $data, $index),
            'subtitle' => $this->getMapperValue('subtitle', $data, $index),
            'thumbnail' => $this->getMapperValue('thumbnail', $data, $index),
            'url' => $this->getMapperValue('url', $data, $index),
            'overlay' => $this->overlay,
            'values' => $values,
        ];
    }

    protected function prepareBeforeRender(): void
    {
        parent::prepareBeforeRender();

        $this->resolvePaginator();

        if ($this->isAsync() && $this->hasPaginator()) {
            $this->paginator(
                $this->getPaginator()
                    ?->setPath($this->prepareAsyncUrlFromPaginator())
            );
        }

        if ($this->isAsync()) {
            $this->customAttributes([
                'data-events' => $this->asyncEvents(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     * @throws Throwable
     */
    protected function viewData(): array
    {
        return [
            'components' => $this->getComponents(),
            'name' => $this->getName(),
            'hasPaginator' => $this->hasPaginator(),
            'paginator' => $this->getPaginator(
                $this->isAsync()
            ),
            'async' => $this->isAsync(),
            'asyncUrl' => $this->getAsyncUrl(),
            'colSpan' => $this->columnSpanValue(),
            'adaptiveColSpan' => $this->adaptiveColumnSpanValue(),
        ];
    }
}
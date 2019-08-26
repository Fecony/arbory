<?php


namespace Arbory\Base\Admin\Filter\Types;

use Arbory\Base\Admin\Filter\FilterItem;
use Arbory\Base\Admin\Filter\Parameters\FilterParameters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Validator;

class DateRangeFilterType extends RangeFilterType
{
    protected $inputType = 'date';

    /**
     * @param FilterItem $filterItem
     * @param Builder $builder
     */
    public function execute(FilterItem $filterItem, Builder $builder): void
    {
        $min = $this->getRangeValue(static::KEY_MIN);
        $max = $this->getRangeValue(static::KEY_MAX);

        if ($min) {
            $min = Carbon::parse($min)->startOfDay()->toDateTimeString();
            $builder->whereDate($filterItem->getName(), '>=', $min);
        }

        if ($max) {
            $max = Carbon::parse($max)->endOfDay()->toDateTimeString();
            $builder->whereDate($filterItem->getName(), '<', $max);
        }
    }

    /**
     * @param FilterParameters $parameters
     * @param callable $attributeResolver
     * @return array
     */
    public function rules(FilterParameters $parameters, callable $attributeResolver): array
    {
        return [
            static::KEY_MIN => ['nullable', 'date'],
            static::KEY_MAX => ['nullable', 'date']
        ];
    }

    /**
     * @param Validator $validator
     * @param FilterParameters $filterParameters
     * @param callable $attributeResolver
     */
    public function withValidator(
        Validator $validator,
        FilterParameters $filterParameters,
        callable $attributeResolver
    ): void {
        $minAttribute = $attributeResolver(static::KEY_MIN);
        $maxAttribute = $attributeResolver(static::KEY_MAX);

        $validator->sometimes($attributeResolver(static::KEY_MIN), "before:{$maxAttribute}",
            static function (Fluent $fluent) use ($maxAttribute) {
                return ! blank(Arr::get($fluent->getAttributes(), $maxAttribute));
            });

        $validator->sometimes($attributeResolver(static::KEY_MAX), "after:{$minAttribute}",
            static function (Fluent $fluent) use ($minAttribute) {
                return ! blank(Arr::get($fluent->getAttributes(), $minAttribute));
            });
    }
}
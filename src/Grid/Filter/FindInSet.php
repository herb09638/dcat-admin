<?php

namespace Dcat\Admin\Grid\Filter;

use Illuminate\Support\Arr;

class FindInSet extends AbstractFilter
{
    /**
     * Input value from presenter.
     *
     * @var mixed
     */
    public $input;

    /**
     * Get condition of this filter.
     *
     * @param  array  $inputs
     * @return array|mixed|void
     */
    public function condition($inputs)
    {
        $value = Arr::get($inputs, $this->column);

        if ($value === null) {
            return;
        }

        $this->input = $this->value = $value;

        $query = function ($query) {
            // Use Grammar to properly quote the column name to prevent SQL injection
            $quotedColumn = $query->getGrammar()->wrap($this->column);
            $query->whereRaw("FIND_IN_SET(?, {$quotedColumn})", [$this->value]);
        };

        return $this->buildCondition($query->bindTo($this));
    }
}

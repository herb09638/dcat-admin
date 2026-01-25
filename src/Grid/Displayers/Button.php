<?php

namespace Dcat\Admin\Grid\Displayers;

use Dcat\Admin\Support\Helper;

class Button extends AbstractDisplayer
{
    public function display($style = 'primary')
    {
        $style = collect((array) $style)->map(function ($style) {
            // Only allow alphanumeric and dash in style names
            return 'btn-'.preg_replace('/[^a-z0-9-]/i', '', $style);
        })->implode(' ');

        // Escape output to prevent XSS
        $escapedValue = Helper::htmlEntityEncode($this->value);

        return "<span class='btn btn-sm {$style}'>{$escapedValue}</span>";
    }
}

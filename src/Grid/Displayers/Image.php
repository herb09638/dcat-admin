<?php

namespace Dcat\Admin\Grid\Displayers;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;

class Image extends AbstractDisplayer
{
    public function display($server = '', $width = 200, $height = 200)
    {
        if ($this->value instanceof Arrayable) {
            $this->value = $this->value->toArray();
        }

        return collect((array) $this->value)->filter()->map(function ($path) use ($server, $width, $height) {
            if (url()->isValidUrl($path) || mb_strpos($path, 'data:image') === 0) {
                $src = $path;
            } elseif ($server) {
                $src = rtrim($server, '/').'/'.ltrim($path, '/');
            } else {
                $src = Storage::disk(config('admin.upload.disk'))->url($path);
            }

            // Escape output to prevent XSS
            $escapedSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
            $width = (int) $width;
            $height = (int) $height;

            return "<img data-action='preview-img' src='{$escapedSrc}' style='max-width:{$width}px;max-height:{$height}px;cursor:pointer' class='img img-thumbnail' />";
        })->implode('&nbsp;');
    }
}

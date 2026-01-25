<?php

namespace Dcat\Admin\Grid\Displayers;

use Dcat\Admin\Admin;
use Dcat\Admin\Support\Helper;

/**
 * Class QRCode.
 */
class QRCode extends AbstractDisplayer
{
    protected static $js = [
        '@qrcode',
    ];

    protected function addScript()
    {
        $script = <<<'JS'
$('.grid-column-qrcode').on('click', function () {
    var $this = $(this), data = $this.data();
    data.render = 'image';
    $this.qrcode(data);
    
    var img = $this.find('img');
    
    $this.attr('data-content', '<img width="'+data.width+'" height="'+data.height+'" src="'+img.attr('src')+'">');
    img.remove();
    
    $this.popover('show')
});
JS;
        Admin::script($script);
    }

    public function display($formatter = null, $width = 150, $height = 150)
    {
        $this->addScript();

        $content = $this->column->getOriginal();

        if ($formatter instanceof \Closure) {
            $content = $formatter->call($this->row, $content);
        }

        // Escape output to prevent XSS
        $escapedContent = htmlspecialchars($content ?? '', ENT_QUOTES, 'UTF-8');
        $escapedValue = Helper::htmlEntityEncode($this->value);
        $width = (int) $width;
        $height = (int) $height;

        return <<<HTML
<a href="javascript:void(0);"
    class="grid-column-qrcode text-muted"
    data-text="{$escapedContent}"
    data-width="{$width}"
    data-height="{$height}"
    data-trigger="trigger"
    data-html="true"
    data-toggle='popover'
    tabindex='0'
>
    <i class="fa fa-qrcode"></i>
</a>&nbsp;{$escapedValue}
HTML;
    }
}

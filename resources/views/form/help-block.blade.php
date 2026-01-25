@if($help)
<span class="help-block">
    <i class="fa {{ e(\Illuminate\Support\Arr::get($help, 'icon', '')) }}"></i>&nbsp;@if(\Illuminate\Support\Arr::get($help, 'text') instanceof \Illuminate\Contracts\Support\Htmlable){!! \Illuminate\Support\Arr::get($help, 'text') !!}@else{{ \Illuminate\Support\Arr::get($help, 'text', '') }}@endif
</span>
@endif
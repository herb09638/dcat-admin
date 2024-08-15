<style>
    .webuploader-pick {
        background-color: @primary;
    }

    .web-uploader .placeholder .flashTip a {
        color: @primary(-10);
    }

    .web-uploader .statusBar .upload-progress span.percentage,
    .web-uploader .filelist li p.upload-progress span {
        background: @primary(-8);
    }

    .web-uploader .dnd-area.webuploader-dnd-over {
        border: 3px dashed #999 !important;
    }
    .web-uploader .dnd-area.webuploader-dnd-over .placeholder {
        border: none;
    }

</style>

<div class="{{$viewClass['form-group']}} {{ $class }}">

    <label for="{{$column}}" class="{{$viewClass['label']}} control-label">{!! $label !!}</label>

    <div class="{{$viewClass['field']}}">

        @include('admin::form.error')

        <input name="{{ $name }}" class="file-input" type="hidden" {!! $attributes !!}/>

        <div class="web-uploader {{ $fileType }}">
            <div class="queueList dnd-area">
                <div class="placeholder">
                    <div class="file-picker"></div>
                    <p>{{trans('admin.uploader.drag_file')}}</p>
                </div>
            </div>
            <div class="statusBar" style="display:none;">
                <div class="upload-progress progress progress-bar-primary pull-left">
                    <div class="progress-bar progress-bar-striped active" style="line-height:18px">0%</div>
                </div>
                <div class="info"></div>
                <div class="btns">
                    <div class="add-file-button"></div>
                    @if($showUploadBtn)
                    &nbsp;
                    <div class="upload-btn btn btn-primary"><i class="feather icon-upload"></i> &nbsp;{{trans('admin.upload')}}</div>
                    @endif
                </div>
            </div>
        </div>

        @include('admin::form.help-block')
    </div>
</div>

<script require="@webuploader,@sortable" init="{!! $selector !!}">
    var uploader,
        newPage,
        options = {!! $options !!},
        events = options.events;

    init();

    function init() {
        var opts = $.extend({
            selector: $this,
            addFileButton: $this.find('.add-file-button'),
            inputSelector: $this.find('.file-input'),
        }, options);

        opts.upload = $.extend({
            pick: {
                id: $this.find('.file-picker'),
                name: '_file_',
                label: '<i class="feather icon-folder"><\/i>&nbsp; {!! trans('admin.uploader.add_new_media') !!}'
            },
            dnd: $this.find('.dnd-area'),
            paste: $this.find('.web-uploader')
        }, opts);

        uploader = Dcat.Uploader(opts);
        uploader.build();
        uploader.preview();

        function updateOrder($sortable) {
            console.log($sortable);
            var el = $($sortable[0].el);
            var fieldName = el.parents().filter(function() {
                return $(this).attr('class') && $(this).attr('class').split(' ').some(function(className) {
                    return className.startsWith('field_');
                });
            }).first();
            console.log(fieldName);
            if(!fieldName) return;

            fieldName = fieldName.attr('class').split('_')[1];
            var newOrder = el.find('li').map(function() {
                return $(this).find('[data-file-act="deleteurl"]').data('id');
            }).get();

            var targetInput = el.parents().find('input[type="hidden"][name*="' + fieldName.trim() + '"][class="file-input"]').first();
            console.log(targetInput);

            if (targetInput.length) {
                targetInput.val(newOrder.join(','));
            } else {
                console.log('找不到符合條件的 input 元素');
            }
        }

        if (options.sortable) {
            $('.filelist').sortable({
                animation: 150,
                handle: '.title, .imgWrap',
                onEnd: function(event, ui) {
                    updateOrder($(this));
                }
            });
        }

        for (var i = 0; i < events.length; i++) {
            var evt = events[i];
            if (evt.event && evt.script) {
                if (evt.once) {
                    uploader.uploader.once(evt.event, evt.script.bind(uploader))
                } else {
                    uploader.uploader.on(evt.event, evt.script.bind(uploader))
                }
            }
        }

        function resize() {
            setTimeout(function () {
                if (! uploader) return;

                uploader.refreshButton();
                resize();

                if (! newPage) {
                    newPage = 1;
                    $(document).one('pjax:complete', function () {
                        uploader = null;
                    });
                }
            }, 250);
        }
        resize();
    }
</script>

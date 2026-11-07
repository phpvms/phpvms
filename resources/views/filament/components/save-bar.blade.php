{{-- Page footer of the save bar (see HasSaveBar::getFooter). --}}
@if ($visible)
    <div class="save-bar-ctn">
        {{ $this->saveBar }}
    </div>
@endif

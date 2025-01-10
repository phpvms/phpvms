<div class="modal fade" id="externalRedirectModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">@lang('common.external_redirection')</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        @lang('common.abouttoleave') <span class="text-primary" id="externalRedirectHost"></span>. @lang('common.wanttocontinue')
        <div class="input-group form-group-no-border mt-2">
          <input id="redirectAlwaysTrustThisDomain" type="checkbox" value="1">
          <label for="redirectAlwaysTrustThisDomain" class="control-label mb-0 ml-2">
            @lang('common.alwaystrustdomain')
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('common.close')</button>
        <a href="#" target="_blank" class="btn btn-primary" id="externalRedirectUrl">@lang('common.continue')</a>
      </div>
    </div>
  </div>
</div>

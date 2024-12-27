<div class="modal fade" id="externalRedirectModal" tabindex="-1" aria-labelledby="externalRedirectModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">@lang('common.external_redirection')</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        @lang('common.abouttoleave') <span class="text-primary" id="externalRedirectHost"></span>. @lang('common.wanttocontinue')

        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" value="" id="redirectAlwaysTrustThisDomain">
          <label class="form-check-label" for="redirectAlwaysTrustThisDomain">
            @lang('common.alwaystrustdomain')
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">@lang('common.close')</button>
        <a href="#" target="_blank" class="btn btn-primary" id="externalRedirectUrl">@lang('common.continue')</a>
      </div>
    </div>
  </div>
</div>

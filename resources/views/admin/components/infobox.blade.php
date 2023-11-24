<div class="card border-blue-bottom">
  <div class="content">
    <div class="row">
      <div class="col-xs-5">
        <div class="icon-big icon-info text-center">
          <i class="{{$icon}}"></i>
        </div>
      </div>
      <div class="col-xs-7">
        <div class="numbers">
          <p>{{$type}}</p>
          @if(isset($link))
            <a href="{{ $link }}">
              @endif
              {{$pending}} pending
              @if(isset($link))
            </a>
          @endif
        </div>
      </div>
    </div>
    <div class="footer">
      <hr>
      @if(isset($total))
        <div class="stats">
          <i class="ti-medall"></i> {{$total}} total
        </div>
      @endif
    </div>
  </div>

</div>

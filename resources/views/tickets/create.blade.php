@extends('layouts.app')

@section('content')
<form class="form-horizontal" action={{url('/tickets')}} role="form" method="POST">
    {!! csrf_field() !!}

    <div class="form-group">
        <label class="col-md-4 control-label">Ticket Type</label>
        <div class="col-md-6">
            <input type="text" class="form-control" name="type">
        </div>
    </div>

    <div class="form-group">
        <label class="col-md-4 control-label">Amount</label>
        <div class="col-md-6">
            <input type="number" class="form-control" name="amount">
        </div>
    </div>

    <div class="form-group">
      <button type="submit" class="btn btn-danger col-md-offset-6">Generate</button>
    </div>

</form>
@endsection

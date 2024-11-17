@extends('system.installer.app')
@section('title', 'User Setup')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div style="align-content: center;">
                <form method="post" action="{{ route('installer.usersetup') }}">
                    @csrf
                    <table class="table" width="25%">

                        <tr>
                            <td colspan="2" style="text-align: right">
                                <a href="{{ route('importer.index') }}">Importing from a legacy install?</a>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2">
                                <h4>Airline Information</h4>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <p>Airline ICAO</p>
                            </td>
                            <td>
                                <div class="form-group">
                                    <input type="text" name="airline_icao" class="form-control" />
                                    @include('system.installer.flash/check_error', [
                                        'field' => 'airline_icao',
                                    ])
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <p>Airline Name</p>
                            </td>
                            <td>
                                <div class="form-group">
                                    <input type="text" name="airline_name" class="form-control" />
                                    @include('system.installer.flash/check_error', [
                                        'field' => 'airline_name',
                                    ])
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <p>Airline Country</p>
                            </td>
                            <td>
                                <div class="form-group">
                                    <select name="airline_country" class="form-control select2">
                                        @foreach ($countries as $code => $name)
                                            <option value="{{ $code }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @include('system.installer.flash/check_error', [
                                        'field' => 'airline_country',
                                    ])
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2">
                                <h4>First User</h4>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <p>Name</p>
                            </td>
                            <td>
                                <div class="form-group">
                                    <input type="text" name="name" class="form-control" />
                                    @include('system.installer.flash/check_error', ['field' => 'name'])
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <p>Email</p>
                            </td>
                            <td>
                                <div class="form-group">
                                    <input type="email" name="email" class="form-control" />
                                    @include('system.installer.flash/check_error', ['field' => 'email'])
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <p>Password</p>
                            </td>
                            <td>
                                <input type="password" name="password" class="form-control" />
                                @include('system.installer.flash/check_error', ['field' => 'password'])
                            </td>
                        </tr>

                        <tr>
                            <td width="40%">
                                <p>Password Confirm</p>
                            </td>
                            <td>
                                <input type="password" name="password_confirmation" class="form-control" />
                                @include('system.installer.flash/check_error', [
                                    'field' => 'password_confirmation',
                                ])
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2">
                                <h4>Options</h4>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <p>Analytics</p>
                            </td>
                            <td>
                                <div class="form-group">
                                    <input type="hidden" name="telemetry" value="0" />
                                    <input type="checkbox" name="telemetry" value="1" checked="checked"
                                        class="form-control" />
                                    <br />
                                    <p>
                                        Allows collection of analytics. They won't identify you, and helps us to track
                                        the PHP and database versions that are used, and help to figure out problems
                                        and slowdowns when vaCentral integration is enabled.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div id="dbtest"></div>
                    <p style="text-align: right">
                        <button type="submit" class="btn btn-success">Complete Setup >></button>
                    </p>
                </form>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ $team->name }} - Servers - {{ $server->name }}</div>

                    <div class="card-body">
                        {{ $server }}

                        @if ( ! $server->isSetUp())
                            <form action="{{ route('server.setup.store', [$team, $server]) }}" method="post">
                                @csrf

                                <label for="password">Server Password for user {{ $server->user }}</label>
                                <input type="password" id="password" name="password" required>
                                @error('password')
                                <p><strong class="text-danger">{{ $message }}</strong></p>
                                @enderror

                                <button type="submit">Setup</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

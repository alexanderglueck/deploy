@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Create server</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                            <form action="{{ route('server.store', [$team]) }}" method="post">
                                @include('server.partials.edit')
                            </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

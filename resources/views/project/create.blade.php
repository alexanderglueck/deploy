@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Create project</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('project.store', [$team]) }}" method="post">
                            @include('project.partials.edit')
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

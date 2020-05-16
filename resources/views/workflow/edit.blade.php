@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Edit workflow</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('workflow.update', [$team, $project, $workflow]) }}" method="post">
                            @method('PUT')
                            @include('workflow.partials.edit', ['buttonText' => 'Edit'])
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

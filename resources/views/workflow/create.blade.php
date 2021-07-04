@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Create workflow</div>

                    <div class="card-body">
                        <form action="{{ route('workflow.store', [$team, $project]) }}" method="post">
                            @include('workflow.partials.edit')
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

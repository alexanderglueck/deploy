@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-body">Dashboard</div>
                </div>

                @foreach($teams as $team)
                    <div class="card mb-3">
                        <div class="card-header"><a href="{{ route('team.show', $team) }}">{{ $team->name }}</a></div>
                        <div class="list-group list-group-flush">
                            @foreach ($team->projects as $project)
                                <a class="list-group-item list-group-item-action"
                                   href="{{ route('project.show', [$team, $project]) }}">
                                    {{ $project->name }}
                                    ({{ route('api.deployment.store', $project->deploy_endpoint) }})
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

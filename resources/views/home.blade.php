@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Projects</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <ul>
                            @foreach($teams as $team)
                                <li>

                                    <a href="{{ route('team.show', $team) }}">{{ $team->name }}</a>
                                    <ul>
                                        @foreach ($team->projects as $project)
                                            <li>
                                                <a href="{{ route('project.show', [$team, $project]) }}">
                                                    {{ $project->name }}
                                                    ({{ route('api.deployment.store', $project->deploy_endpoint) }})
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

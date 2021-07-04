@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-body">{{ $team->name }}</div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        Servers
                        <a class="btn btn-primary btn-sm" href="{{ route('server.create', [$team]) }}">Create server</a>
                    </div>

                    <div class="list-group list-group-flush">
                        @foreach ($servers as $server)
                            <a class="list-group-item list-group-item-action"
                               href="{{ route('server.show', [$team, $server]) }}">
                                {{ $server->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="card ">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        Projects
                        <a class="btn btn-primary btn-sm" href="{{ route('project.create', [$team]) }}">Create project</a>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($projects as $project)
                            <a class="list-group-item list-group-item-action"
                               href="{{ route('project.show', [$team, $project]) }}">
                                {{ $project->name }}
                                ({{ route('api.deployment.store', $project->deploy_endpoint) }})
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

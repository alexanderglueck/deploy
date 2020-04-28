@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ $team->name }}</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <h2>Servers</h2>
                        <a href="{{ route('server.create', [$team]) }}">Create server</a>

                        @foreach ($servers as $server)
                            <li>
                                <a href="{{ route('server.show', [$team, $server]) }}">
                                    {{ $server->name }}
                                </a>
                            </li>
                        @endforeach

                        <h2>Projects</h2>
                        <a href="{{ route('project.create', [$team]) }}">Create project</a>

                        @foreach ($projects as $project)
                            <li>
                                <a href="{{ route('project.show', [$team, $project]) }}">
                                    {{ $project->name }}
                                    ({{ route('api.deployment.store', $project->deploy_endpoint) }})
                                </a>
                            </li>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

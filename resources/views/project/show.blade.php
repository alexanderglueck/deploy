@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card mb-2">
                    <div class="card-header">{{ $project->name }} ({{ route('api.deployment.store', $project->deploy_endpoint) }})</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <a href="{{ route('workflow.create', [$team, $project]) }}">Create workflow</a>

                        <ul>
                            @foreach($workflows as $workflow)
                                <li>
                                    <details>
                                        <summary>
                                            Workflow {{ $loop->iteration }} on {{ $workflow->server->name }}
                                        </summary>
                                        <pre class="text-white bg-dark"><samp>{{ $workflow->actions }}</samp></pre>
                                        <div>

                                            <form
                                                action="{{ route('workflow.destroy', [$team, $project, $workflow]) }}"
                                                method="post"
                                            >
                                                <a href="{{ route('workflow.edit', [$team, $project, $workflow]) }}" class="btn btn-outline-secondary">
                                                    Edit
                                                </a>

                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Deployments {{ $project->name }}</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <ul>
                            @foreach($deployments as $deployment)
                                <li>
                                    Received at: {{ $deployment->received_at }}<br>
                                    Processed at: {{ $deployment->processed_at }}<br>
                                    Deployed at: {{ $deployment->deployed_at }}<br>
                                    Canceled at: {{ $deployment->canceled_at }}

                                    <details>
                                        <summary>
                                            Click for executed actions
                                        </summary>
                                        <pre class="text-white bg-dark"><samp>{{ $deployment->actions }}</samp></pre>
                                    </details>

                                    <details>
                                        <summary>
                                            Logs
                                        </summary>
                                        <pre class="text-white bg-dark"><samp>{{ $deployment->log->log }}</samp></pre>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

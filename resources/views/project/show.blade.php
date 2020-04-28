@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card mb-2">
                    <div class="card-header">{{ $project->name }}</div>

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

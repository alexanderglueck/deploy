@csrf

<div class="form-group">
    <label for="name">Name</label>
    <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" name="name"
           value="{{ old('name', $server->name) }}" required>
    @error('name')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label for="user">User</label>
    <input type="text" id="user" name="user" class="form-control @error('user') is-invalid @enderror"
           value="{{ old('user', $server->user) }}" required>
    @error('user')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label for="ip">IP</label>
    <input type="text" id="ip" name="ip" class="form-control @error('ip') is-invalid @enderror" value="{{ old('ip', $server->ip) }}" required>
    @error('ip')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label for="port">Port</label>
    <input type="text" id="port" name="port" class="form-control @error('port') is-invalid @enderror" value="{{ old('port', $server->port) }}" required>
    @error('port')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<button type="submit" class="btn btn-primary">{{ $buttonText ?? 'Create' }}</button>

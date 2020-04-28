@csrf

<label for="name">Name</label>
<input type="text" id="name" name="name" value="{{ old('name', $server->name) }}" required>
@error('name')
<p><strong class="text-danger">{{ $message }}</strong></p>
@enderror

<label for="user">User</label>
<input type="text" id="user" name="user" value="{{ old('user', $server->user) }}" required>
@error('user')
<p><strong class="text-danger">{{ $message }}</strong></p>
@enderror

<label for="ip">IP</label>
<input type="text" id="ip" name="ip" value="{{ old('ip', $server->ip) }}" required>
@error('ip')
<p><strong class="text-danger">{{ $message }}</strong></p>
@enderror

<label for="port">Port</label>
<input type="text" id="port" name="port" value="{{ old('port', $server->port) }}" required>
@error('port')
<p><strong class="text-danger">{{ $message }}</strong></p>
@enderror

<button type="submit">{{ $buttonText ?? 'Create' }}</button>

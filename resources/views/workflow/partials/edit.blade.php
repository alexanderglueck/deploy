@csrf

<label for="event">Event</label>
<select name="event" id="event" required>
    <option value="{{ \App\Event::PUSH }}" {{ old('event', $workflow->event) == \App\Event::PUSH ? ' selected' : '' }}>
        Push
    </option>
</select>
@error('event')
<p><strong class="text-danger">{{ $message }}</strong></p>
@enderror

<label for="server_id">Server</label>
<select name="server_id" id="server_id" required>
    @foreach ($servers as $server)
        <option
            value="{{ $server->id }}" {{ old('server_id', $workflow->server_id) == $server->id ? ' selected' : '' }}>
            {{ $server->name }}
        </option>
    @endforeach
</select>
@error('event')
<p><strong class="text-danger">{{ $message }}</strong></p>
@enderror

<label for="actions">Actions</label>
<textarea id="actions" name="actions" required>{{ old('actions', $project->actions) }}</textarea>
@error('actions')
<p><strong class="text-danger">{{ $message }}</strong></p>
@enderror

<button type="submit">{{ $buttonText ?? 'Create' }}</button>

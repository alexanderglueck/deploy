@csrf
<div class="form-group">
    <label for="event">Event</label>
    <select name="event" id="event" class="form-control @error('event') is-invalid @enderror" required>
        <option
            value="{{ \App\Event::PUSH }}" {{ old('event', $workflow->event) == \App\Event::PUSH ? ' selected' : '' }}>
            Push
        </option>
    </select>
    @error('event')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label for="server_id">Server</label>
    <select name="server_id" id="server_id" class="form-control @error('server_id') is-invalid @enderror" required>
        @foreach ($servers as $server)
            <option
                value="{{ $server->id }}" {{ old('server_id', $workflow->server_id) == $server->id ? ' selected' : '' }}>
                {{ $server->name }}
            </option>
        @endforeach
    </select>
    @error('server_id')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <label for="actions">Actions</label>
    <textarea id="actions" name="actions" class="form-control @error('actions') is-invalid @enderror"
              required>{{ old('actions', $workflow->actions) }}</textarea>
    @error('actions')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<button type="submit" class="btn btn-primary">{{ $buttonText ?? 'Create' }}</button>

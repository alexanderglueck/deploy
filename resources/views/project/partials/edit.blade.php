@csrf

<label for="name">Name</label>
<input type="text" id="name" name="name" value="{{ old('name', $project->name) }}" required>

<button type="submit">{{ $buttonText ?? 'Create' }}</button>

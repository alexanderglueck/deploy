@csrf

<label for="name">Name</label>
<input type="text" id="name" name="name" value="{{ old('name', $project->name) }}" required>
@error('name')
<p><strong class="text-danger">{{ $message }}</strong></p>
@enderror

<button type="submit">{{ $buttonText ?? 'Create' }}</button>

@extends('layouts.app')

@section('title', 'Create File')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">Create New File</h1>
    <a href="{{ route('files.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Back to List</a>
</div>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="{{ route('files.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="file_number" class="block text-sm font-medium text-gray-700">File Number <span class="text-red-500">*</span></label>
            <input type="text" name="file_number" id="file_number" value="{{ old('file_number') }}" required maxlength="50" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('file_number') border-red-500 @enderror">
            @error('file_number')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" id="title" value="{{ old('title') }}" required maxlength="255" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('title') border-red-500 @enderror">
            @error('title')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
            <textarea name="description" id="description" rows="3" maxlength="1000" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700">Category <span class="text-red-500">*</span></label>
                <input type="text" name="category" id="category" value="{{ old('category', 'general') }}" required maxlength="100" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('category') border-red-500 @enderror">
                @error('category')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                <select name="status" id="status" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('status') border-red-500 @enderror">
                    <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="expired" {{ old('status') == 'expired' ? 'selected' : '' }}>Expired</option>
                    <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="effective_date" class="block text-sm font-medium text-gray-700">Effective Date</label>
                <input type="date" name="effective_date" id="effective_date" value="{{ old('effective_date') }}" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('effective_date') border-red-500 @enderror">
                @error('effective_date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expiry Date</label>
                <input type="date" name="expiry_date" id="expiry_date" value="{{ old('expiry_date') }}" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('expiry_date') border-red-500 @enderror">
                @error('expiry_date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="file_path" class="block text-sm font-medium text-gray-700">File Path</label>
                <input type="text" name="file_path" id="file_path" value="{{ old('file_path') }}" maxlength="500" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('file_path') border-red-500 @enderror">
                @error('file_path')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="file_type" class="block text-sm font-medium text-gray-700">File Type</label>
                <input type="text" name="file_type" id="file_type" value="{{ old('file_type') }}" maxlength="50" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('file_type') border-red-500 @enderror">
                @error('file_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div>
            <label for="file_size" class="block text-sm font-medium text-gray-700">File Size (bytes)</label>
            <input type="number" name="file_size" id="file_size" value="{{ old('file_size') }}" min="0" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('file_size') border-red-500 @enderror">
            @error('file_size')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="tags" class="block text-sm font-medium text-gray-700">Tags (comma-separated)</label>
            <input type="text" name="tags" id="tags" value="{{ old('tags') }}" placeholder="e.g. rabies, vaccination, protocol" class="mt-1 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('tags') border-red-500 @enderror">
            @error('tags')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">Create File</button>
            <a href="{{ route('files.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg text-sm transition">Cancel</a>
        </div>
    </form>
</div>
@endsection
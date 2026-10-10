@extends('layouts.app')

@section('title', 'File Details')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">File Details</h1>
    <div class="flex gap-2">
        <a href="{{ route('files.edit', $file) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Edit</a>
        <form action="{{ route('files.destroy', $file) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?')">
            @csrf @method('DELETE')
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Delete</button>
        </form>
    </div>
</div>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow p-6 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">{{ $file->title }}</h2>
            <p class="text-sm text-gray-500">{{ $file->file_number }}</p>
        </div>
        <div class="flex gap-3">
            <span class="px-3 inline-flex text-sm leading-5 font-semibold rounded-full
                @if($file->status === 'active') bg-green-100 text-green-800
                @elseif($file->status === 'expired') bg-red-100 text-red-800
                @elseif($file->status === 'archived') bg-gray-100 text-gray-800
                @elseif($file->status === 'draft') bg-yellow-100 text-yellow-800
                @else bg-gray-100 text-gray-800
                @endif">
                {{ $file->statusLabel() }}
            </span>
            <span class="px-3 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                {{ $file->category }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Description</h3>
            <p class="text-sm text-gray-900">{{ $file->description ?? 'No description provided.' }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">File Information</h3>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">File Path</dt><dd class="text-gray-900">{{ $file->file_path ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">File Type</dt><dd class="text-gray-900">{{ $file->file_type ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">File Size</dt><dd class="text-gray-900">{{ $file->file_size ? number_format($file->file_size) . ' bytes' : '-' }}</dd></div>
            </dl>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Dates</h3>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Effective Date</dt><dd class="text-gray-900">{{ $file->effective_date?->format('M d, Y') ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Expiry Date</dt><dd class="text-gray-900">{{ $file->expiry_date?->format('M d, Y') ?? '-' }}</dd></div>
            </dl>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Tags</h3>
            <p class="text-sm text-gray-900">
                @if($file->tags && count($file->tags) > 0)
                    @foreach($file->tags as $tag)
                        <span class="inline-block bg-gray-100 rounded-full px-2 py-1 text-xs font-medium text-gray-700 mr-1">{{ $tag }}</span>
                    @endforeach
                @else
                    No tags
                @endif
            </p>
        </div>
    </div>

    <div class="mt-6 pt-6 border-t border-gray-200">
        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Audit Trail</h3>
        <dl class="space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Created By</dt><dd class="text-gray-900">{{ $file->creator?->name ?? 'System' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Updated By</dt><dd class="text-gray-900">{{ $file->updater?->name ?? '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Created</dt><dd class="text-gray-900">{{ $file->created_at->format('M d, Y H:i') }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Last Updated</dt><dd class="text-gray-900">{{ $file->updated_at->format('M d, Y H:i') }}</dd></div>
        </dl>
    </div>
</div>
@endsection
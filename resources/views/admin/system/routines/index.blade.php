@extends('layouts.admin')

@section('title', 'ルーティン管理')

@section('content')
@php
    $isItems = $tab === 'items';
    $statusLabels = ['未作成' => 'not-started', '作成中' => 'drafting', '作成済' => 'done', '不要' => 'none'];
    $difficultyTextToStar = ['非常に低い'=>1, '低い'=>2, '標準'=>3, '高い'=>4, '非常に高い'=>5];
    $formatMinutes = function ($minutes) {
        $minutes = (int) $minutes;
        if ($minutes < 60) return $minutes . '分';
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        return $rest > 0 ? $hours . '時間' . $rest . '分' : $hours . '時間';
    };
    $stars = function ($level) {
        $level = max(1, min(5, (int) $level));
        return str_repeat('★', $level) . str_repeat('☆', 5 - $level);
    };
    $tagList = fn ($value) => collect(explode(',', (string) ($value ?? '')))->map(fn($v) => trim($v))->filter()->values();
    $json = fn ($value) => e(json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
@endphp

<div class="routine-page"
     data-csrf-token="{{ csrf_token() }}"
     data-item-update-url-base="{{ url('/admin/system/routine-management/items') }}"
     data-item-store-url="{{ route('admin.system.routines.items.store') }}"
     data-routine-store-url="{{ route('admin.system.routines.store') }}"
     data-routine-update-url-base="{{ url('/admin/system/routine-management/packages') }}"
     data-routine-items-sync-url-base="{{ url('/admin/system/routine-management/packages') }}">
    <div class="routine-header">
        <div>
            <h1>ルーティン管理</h1>
            <p>共通ルーティンアイテムとルーティンを管理します。</p>
        </div>

    </div>

    @if(session('status'))
        <div class="routine-alert">{{ session('status') }}</div>
    @endif

    <div class="routine-tabs">
        <a class="{{ $isItems ? 'active' : '' }}" href="{{ route('admin.system.routines', ['tab' => 'items']) }}">ルーティンアイテム</a>
        <a class="{{ ! $isItems ? 'active' : '' }}" href="{{ route('admin.system.routines', ['tab' => 'routines']) }}">ルーティン</a>
    </div>

    @if($isItems)
        @include('admin.system.routines.tabs.routine-items')
    @else
        @include('admin.system.routines.tabs.routines')
    @endif

    @include('admin.system.routines.modals.detail')
    @include('admin.system.routines.modals.package-items')
</div>

{{-- ルーティン管理専用JS（キャッシュ回避のため画面側でも明示読込） --}}
<script src="{{ asset('js/admin/routine-management.js') }}?v=38"></script>

@endsection

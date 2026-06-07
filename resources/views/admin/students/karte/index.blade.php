@extends('layouts.admin')

@php
    $tab = request('tab', 'basic');
@endphp

@section('content')

<div class="breadcrumb">
    ホーム ＞ 会員管理 ＞ 生徒一覧 ＞ 生徒カルテ
</div>

<div class="page-title">
    生徒カルテ
</div>

@include('admin.students.karte.partials.common.student_header')
@include('admin.students.karte.partials.common.alerts')
@include('admin.students.karte.partials.common.tabs')

@if($tab === 'basic')
    @include('admin.students.karte.tabs.basic')
@elseif($tab === 'routine')
    @include('admin.students.karte.tabs.routine')
@elseif($tab === 'learning_plan')
    @include('admin.students.karte.tabs.learning_plan')
@elseif($tab === 'lesson')
    @include('admin.students.karte.tabs.lesson')
@elseif($tab === 'growth')
    @include('admin.students.karte.tabs.growth')
@elseif($tab === 'event')
    @include('admin.students.karte.tabs.event')
@elseif($tab === 'billing')
    @include('admin.students.karte.tabs.billing')
@elseif($tab === 'contact')
    @include('admin.students.karte.tabs.contact')
@endif

@endsection
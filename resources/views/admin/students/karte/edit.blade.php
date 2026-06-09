@extends('layouts.admin')

@section('content')
<div class="page">

    <div class="breadcrumb">
        ホーム ＞ 会員管理 ＞ 生徒一覧 ＞ 生徒カルテ ＞ 編集
    </div>

    <h1 class="page-title">生徒情報編集</h1>

    <div class="card">
        <form method="POST" action="{{ route('admin.students.karte.update', $student) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">

                <div class="form-group">
                    <label>姓（ふりがな）</label>
                    <input
                        type="text"
                        name="last_name_kana"
                        value="{{ old('last_name_kana', $student->last_name_kana) }}">
                </div>

                <div class="form-group">
                    <label>名（ふりがな）</label>
                    <input
                        type="text"
                        name="first_name_kana"
                        value="{{ old('first_name_kana', $student->first_name_kana) }}">
                </div>

                <div class="form-group">
                    <label>性別</label>
                    <input
                        type="text"
                        name="gender"
                        value="{{ old('gender', $student->gender) }}">
                </div>

                <div class="form-group">
                    <label>生年月日</label>
                    <input
                        type="date"
                        name="birthday"
                        value="{{ old('birthday', $student->birthday) }}">
                </div>

                <div class="form-group">
                    <label>学校名</label>
                    <input
                        type="text"
                        name="school_name"
                        value="{{ old('school_name', $student->school_name) }}">
                </div>

                <div class="form-group">
                    <label>通塾曜日</label>
                    <input
                        type="text"
                        name="commute_days"
                        value="{{ old('commute_days', $student->commute_days) }}">
                </div>

                <div class="form-group">
                    <label>入塾経路</label>
                    <input
                        type="text"
                        name="admission_source"
                        value="{{ old('admission_source', $student->admission_source) }}">
                </div>

                <div class="form-group full">
                    <label>医療・配慮事項</label>
                    <textarea
                        name="medical_notes"
                        rows="4">{{ old('medical_notes', $student->medical_notes) }}</textarea>
                </div>

                <div class="form-group full">
                    <label>備考</label>
                    <textarea
                        name="remarks"
                        rows="5">{{ old('remarks', $student->remarks) }}</textarea>
                </div>

            </div>

            <div class="form-actions">
                <a href="{{ route('admin.students.karte.show', $student) }}"
                   class="btn btn-outline">
                    戻る
                </a>

                <button type="submit"
                        class="btn btn-primary">
                    保存する
                </button>
            </div>

        </form>
    </div>

</div>
@endsection
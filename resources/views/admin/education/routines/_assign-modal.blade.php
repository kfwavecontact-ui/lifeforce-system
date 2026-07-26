<dialog id="assignModal" class="er-modal">
<form method="post" action="{{ route('admin.education.routines.assign') }}" id="assignForm">
    @csrf
    <input type="hidden" name="routine_package_id" id="assignPackageId">
    <div class="er-modal-head">
        <div><h2>ルーティンを割り当てる</h2><p id="assignPackageName"></p></div>
        <button type="button" class="js-close-modal" aria-label="閉じる">×</button>
    </div>
    <div class="er-form-grid">
        <label>対象生徒
            <select name="student_ids[]" multiple required id="assignStudents">
                @foreach($assignmentStudents as $s)
                    <option value="{{ $s->id }}" @selected(optional($selectedStudent)->id===$s->id)>{{ $s->student_name }}（{{ $s->school_name ?: '教室未設定' }}）</option>
                @endforeach
            </select>
            <small>Ctrlキーで複数選択できます。</small>
        </label>
        <label>生徒用ルーティン名<input name="name" id="assignName" maxlength="255"></label>
        <label>開始日<input type="date" name="start_date" value="{{ today()->toDateString() }}" required></label>
        <label>終了日<input type="date" name="end_date"></label>
        <label class="full">生徒向け説明<textarea name="description" id="assignDescription" rows="3"></textarea></label>
        <label class="full er-check"><input type="hidden" name="allow_duplicate" value="0"><input type="checkbox" name="allow_duplicate" value="1"> 同じルーティンが割当済みでも別ルーティンとして追加する</label>
    </div>
    <div class="er-warning" id="assignWarnings"></div>
    <section class="er-assignment-items">
        <div class="er-panel-title">構成アイテム設定</div>
        <div id="assignItems"></div>
    </section>
    <div class="er-modal-actions"><button type="button" class="js-close-modal">キャンセル</button><button class="er-primary">割当を確定</button></div>
</form>
</dialog>

{{-- resources/views/admin/students/karte/partials/common/student_header.blade.php --}}

@php
    use Illuminate\Support\Facades\Storage;

    $studentName = trim(($student->last_name ?? '') . ' ' . ($student->first_name ?? ''));
    $studentName = $studentName !== '' ? $studentName : '未設定';

    $studentKana = trim(($student->last_name_kana ?? '') . ' ' . ($student->first_name_kana ?? ''));
    $studentKana = $studentKana !== '' ? $studentKana : 'ふりがな未設定';

    $studentCode = $student->student_code ?? '未設定';

    $enrolledAt = $student->enrolled_at ?? null;
    $enrolledDate = $enrolledAt ? \Carbon\Carbon::parse($enrolledAt)->format('Y/m/d') : '未設定';

    $enrollmentPeriod = '未設定';
    if ($enrolledAt) {
        $diff = \Carbon\Carbon::parse($enrolledAt)->diff(now());
        $enrollmentPeriod = ($diff->y > 0 ? $diff->y . '年' : '') . $diff->m . 'ヶ月';
    }

    $birthDateRaw = $student->birthday ?? null;
    $birthDate = $birthDateRaw ? \Carbon\Carbon::parse($birthDateRaw)->format('Y/m/d') : '未設定';

    $grade = optional($student->grade)->name ?? '未設定';
    $schoolName = optional($student->school)->name ?? $student->school_name ?? '未設定';

    $teacher = $student->studentTeachers
        ->where('is_active', true)
        ->where('is_primary', true)
        ->sortByDesc('id')
        ->first();

    $teacherName = optional(optional($teacher)->teacher)->last_name
        ? optional($teacher->teacher)->last_name . ' ' . optional($teacher->teacher)->first_name
        : '未設定';

    $statusLabel = optional($student->enrollmentStatus)->status ?? '在籍中';

    $statusClass = match ($statusLabel) {
        '体験中' => 'status-trial',
        '在籍'   => 'status-enrolled',
        '休会'   => 'status-suspended',
        '退会'   => 'status-withdrawn',
        '卒業'   => 'status-graduated',
        default => 'status-enrolled',
    };

    $activeContract = $student->courseContracts
        ->where('is_active', true)
        ->sortByDesc('id')
        ->first();

    $courseName = '未設定';
    if ($activeContract) {
        $course = optional($activeContract->course)->name;
        $attendanceType = optional($activeContract->coursePrice)->attendance_type;

        $courseName = $course ?: '未設定';

        if ($course && $attendanceType) {
            $courseName .= '（' . $attendanceType . '）';
        }
    }

    $attendanceCount = $student->attendances->where('attendance_status', 'attended')->count();
    $absenceCount = $student->attendances->where('attendance_status', 'absent')->count();
    $totalAttendanceTarget = $attendanceCount + $absenceCount;
    $attendanceRate = $totalAttendanceTarget > 0 ? round(($attendanceCount / $totalAttendanceTarget) * 100, 1) : 0;

    $currentPoints = optional($student->pointBalance)->current_points ?? 0;

    $monthlyPointDiff = $student->pointTransactions
        ->filter(fn($transaction) => $transaction->occurred_at && \Carbon\Carbon::parse($transaction->occurred_at)->isCurrentMonth())
        ->sum('points');

    $badgeCount = $student->studentBadges->count();

    $monthlyBadgeDiff = $student->studentBadges
        ->filter(fn($badge) => $badge->acquired_at && \Carbon\Carbon::parse($badge->acquired_at)->isCurrentMonth())
        ->count();

    $equippedTitle = $student->titles->first(fn($title) => optional($title->pivot)->is_equipped);
    $currentTitle = $equippedTitle->name ?? '未設定';
@endphp

<div class="karte-header-layout">

    <div class="karte-student-card">
        <button type="button"
                class="karte-edit-button"
                onclick="document.getElementById('studentInfoModal').classList.add('is-open')">
            ✏ 編集
        </button>

        <div class="karte-student-profile">
            <div class="karte-student-photo-wrap">
                @if($student->profile_image_path)
                    <img
                        src="{{ Storage::disk('s3')->temporaryUrl($student->profile_image_path, now()->addMinutes(30)) }}"
                        alt="{{ $student->last_name }}{{ $student->first_name }}"
                        class="karte-student-photo">
                @else
                    <span class="karte-student-photo-placeholder">👦</span>
                @endif
            </div>

            <div class="karte-student-main">
                <div class="karte-student-title-row">
                    <h1 class="karte-student-name">
                        {{ $studentName }}
                        <span>{{ $studentKana }}</span>
                    </h1>
                    <span class="karte-status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>

                <div class="karte-student-info-grid">
                    <div class="karte-info-label">生徒ID</div>
                    <div class="karte-info-value">{{ $studentCode }}</div>

                    <div class="karte-info-label">所属教室</div>
                    <div class="karte-info-value">{{ $schoolName }}</div>

                    <div class="karte-info-label">学年</div>
                    <div class="karte-info-value">{{ $grade }}</div>

                    <div class="karte-info-label">コース</div>
                    <div class="karte-info-value">{{ $courseName }}</div>

                    <div class="karte-info-label">生年月日</div>
                    <div class="karte-info-value">{{ $birthDate }}</div>

                    <div class="karte-info-label">担当講師</div>
                    <div class="karte-info-value">{{ $teacherName }}</div>
                </div>

                <div class="karte-student-divider"></div>

                <div class="karte-bottom-info">
                    <div class="karte-info-label">入会日</div>
                    <div class="karte-info-value">{{ $enrolledDate }}</div>

                    <div class="karte-info-label">在籍期間</div>
                    <div class="karte-info-value">{{ $enrollmentPeriod }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="karte-summary-wrapper">
        <div class="karte-summary-cards">

            <div class="karte-summary-card">
                <div class="karte-summary-head">
                    <div class="karte-summary-icon blue">📅</div>
                    <div class="karte-summary-label">出席率</div>
                </div>

                <div class="karte-summary-number">{{ $attendanceRate }}<span>%</span></div>

                <div class="karte-progress">
                    <div class="karte-progress-bar" style="width: {{ $attendanceRate }}%;"></div>
                </div>

                <div class="karte-summary-small">
                    出席 {{ $attendanceCount }} / 欠席 <strong>{{ $absenceCount }}</strong>
                </div>
                <div class="karte-summary-note">直近3ヶ月</div>
            </div>

            <div class="karte-summary-card">
                <div class="karte-summary-head">
                    <div class="karte-summary-icon orange">🪙</div>
                    <div class="karte-summary-label">保有ポイント</div>
                </div>

                <div class="karte-summary-number">{{ number_format($currentPoints) }}<span> pt</span></div>
                <div class="karte-summary-gain">今月 +{{ number_format($monthlyPointDiff) }}pt</div>
            </div>

            <div class="karte-summary-card">
                <div class="karte-summary-head">
                    <div class="karte-summary-icon purple">🏅</div>
                    <div class="karte-summary-label">獲得バッジ数</div>
                </div>

                <div class="karte-summary-number">{{ $badgeCount }}<span> 個</span></div>
                <div class="karte-summary-gain">今月 +{{ $monthlyBadgeDiff }}</div>
                <a href="#" class="karte-summary-button">バッジ一覧</a>
            </div>

            <div class="karte-summary-card">
                <div class="karte-summary-head">
                    <div class="karte-summary-icon red">👑</div>
                    <div class="karte-summary-label">現在の称号</div>
                </div>

                <div class="karte-current-title">{{ $currentTitle }}</div>
                <a href="#" class="karte-summary-button">称号一覧</a>
            </div>

        </div>
    </div>

</div>

<div id="studentInfoModal" class="karte-modal">
    <div class="karte-modal-panel">
        <div class="karte-modal-header">
            <h2>生徒情報編集</h2>
            <button type="button"
                    class="karte-modal-close"
                    onclick="document.getElementById('studentInfoModal').classList.remove('is-open')">
                ×
            </button>
        </div>

        <form method="POST"
              action="{{ route('admin.students.karte.update', $student) }}"
              enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="karte-modal-body">

                <div class="form-group full">
                    <label>顔写真</label>
                    <input type="file" name="profile_image" accept="image/*">
                </div>

                <div class="form-group">
                    <label>姓</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}">
                </div>

                <div class="form-group">
                    <label>名</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}">
                </div>

                <div class="form-group">
                    <label>姓（ふりがな）</label>
                    <input type="text" name="last_name_kana" value="{{ old('last_name_kana', $student->last_name_kana) }}">
                </div>

                <div class="form-group">
                    <label>名（ふりがな）</label>
                    <input type="text" name="first_name_kana" value="{{ old('first_name_kana', $student->first_name_kana) }}">
                </div>

                <div class="form-group">
                    <label>生年月日</label>
                    <input type="date" name="birthday" value="{{ old('birthday', $student->birthday) }}">
                </div>

                <div class="form-group">
                    <label>学年</label>
                    <select name="grade_id">
                        @foreach($grades as $gradeItem)
                            <option value="{{ $gradeItem->id }}"
                                @selected(old('grade_id', $student->grade_id) == $gradeItem->id)>
                                {{ $gradeItem->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>所属教室</label>
                    <select name="school_id">
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}"
                                @selected(old('school_id', $student->school_id) == $school->id)>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>コース</label>
                    <select name="course_price_id">
                        @foreach($coursePrices as $coursePrice)
                            <option value="{{ $coursePrice->id }}"
                                @selected(old('course_price_id', optional($activeContract)->course_price_id) == $coursePrice->id)>
                                {{ optional($coursePrice->course)->name }}（{{ $coursePrice->attendance_type }}）
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>担当講師</label>
                    <select name="teacher_id">
                        <option value="">未設定</option>
                        @foreach($teachers as $teacherItem)
                            <option value="{{ $teacherItem->id }}"
                                @selected(old('teacher_id', optional(optional($teacher)->teacher)->id) == $teacherItem->id)>
                                {{ $teacherItem->last_name }} {{ $teacherItem->first_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>在籍状態</label>
                    <select name="enrollment_status_id">
                        @foreach($enrollmentStatuses as $status)
                            <option value="{{ $status->id }}"
                                @selected(old('enrollment_status_id', $student->enrollment_status_id) == $status->id)>
                                {{ $status->status }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>入会日</label>
                    <input type="date" name="enrolled_at" value="{{ old('enrolled_at', $student->enrolled_at) }}">
                </div>

            </div>

            <div class="karte-modal-footer">
                <button type="button"
                        class="modal-btn-cancel"
                        onclick="document.getElementById('studentInfoModal').classList.remove('is-open')">
                    閉じる
                </button>

                <button type="submit" class="modal-btn-save">
                    保存
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.karte-header-layout {
    display: grid;
    grid-template-columns: minmax(0, 55fr) minmax(0, 45fr);
    gap: 20px;
    margin-top: -20px;
    margin-bottom: 18px;
    width: 100%;
    box-sizing: border-box;
}

.karte-student-card,
.karte-summary-wrapper {
    background: #ffffff;
    border: 1px solid #e1e8f5;
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(15, 35, 90, 0.04);
    width: 100%;
    box-sizing: border-box;
}

.karte-student-card {
    padding: 24px;
    position: relative;
}

.karte-student-profile {
    display: grid;
    grid-template-columns: 136px 1fr;
    gap: 26px;
    align-items: flex-start;
}

.karte-student-photo-wrap {
    width: 128px;
    height: 128px;
    border-radius: 50%;
    background: #eef4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 58px;
}

.karte-student-photo{
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
}

.karte-student-photo-placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.karte-student-title-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
    padding-right: 92px;
}

.karte-student-name {
    margin: 0;
    font-size: 28px;
    line-height: 1;
    font-weight: 800;
    color: #07195f;
}

.karte-student-name span {
    font-size: 13px;
    font-weight: 700;
    margin-left: 10px;
    color: #07195f;
}

.karte-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 30px;
    padding: 0 14px;
    border-radius: 999px;
    background: #cdeedb;
    color: #078642;
    font-size: 14px;
    font-weight: 800;
    white-space: nowrap;
}

.karte-student-info-grid {
    display: grid;
    grid-template-columns: 80px 170px 80px 1fr;
    row-gap: 8px;
    column-gap: 14px;
    font-size: 14px;
    width: 100%;
}

.karte-edit-button{
    position:absolute;
    top:24px;
    right:24px;

    display:inline-flex;
    align-items:center;
    justify-content:center;

    height:36px;
    padding:0 14px;

    border:1px solid #d1d9e6;
    border-radius:8px;

    background:#fff;
    color:#374151;

    text-decoration:none;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
}

.karte-edit-button:hover{
    background:#f8fafc;
    border-color:#94a3b8;
}

.karte-status-badge.status-trial{
    background:#dbeafe;
    color:#1d4ed8;
}

.karte-status-badge.status-enrolled{
    background:#dcfce7;
    color:#15803d;
}

.karte-status-badge.status-suspended{
    background:#fef3c7;
    color:#b45309;
}

.karte-status-badge.status-withdrawn{
    background:#fee2e2;
    color:#dc2626;
}

.karte-status-badge.status-graduated{
    background:#fee2e2;
    color:#dc2626;
}


.karte-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.55);
    align-items: center;
    justify-content: center;
}

.karte-modal.is-open {
    display: flex;
}

.karte-modal-panel {
    width: 720px;
    max-width: calc(100vw - 32px);
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
}

.karte-modal-header {
    height: 64px;
    padding: 0 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.karte-modal-header h2 {
    font-size: 18px;
    color: #07195f;
}

.karte-modal-close {
    border: none;
    background: #eef2f7;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    font-size: 22px;
    cursor: pointer;
}

.karte-modal-body {
    padding: 24px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.karte-modal-body .full {
    grid-column: 1 / -1;
}

.karte-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.modal-btn-cancel,
.modal-btn-save {
    height: 38px;
    padding: 0 18px;
    border-radius: 8px;
    border: none;
    font-weight: 800;
    cursor: pointer;
}

.modal-btn-cancel {
    background: #e5e7eb;
    color: #1f2937;
}

.modal-btn-save {
    background: #2563eb;
    color: #fff;
}

.karte-info-label {
    color: #4b5a8a;
    font-weight: 700;
}

.karte-info-value {
    color: #07195f;
    font-weight: 800;
}

.karte-student-divider {
    margin: 14px 0 12px;
    border-top: 1px solid #e5e7eb;
}

.karte-bottom-info {
    display: grid;
    grid-template-columns: 80px 170px 80px 1fr;
    column-gap: 14px;
    align-items: center;
    font-size: 14px;
    width: 100%;
}

.karte-summary-wrapper {
    overflow: hidden;
}

.karte-summary-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    height: 100%;
    background: #ffffff;
}

.karte-summary-card {
    min-height: 192px;
    padding: 20px 16px 18px;
    border-right: 1px solid #edf2fb;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.karte-summary-card:last-child {
    border-right: none;
}

.karte-summary-head {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 28px;
    width: 100%;
}

.karte-summary-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
}

.karte-summary-icon.blue { background: #eaf1ff; }
.karte-summary-icon.orange { background: #fff1e5; }
.karte-summary-icon.purple { background: #efe8ff; }
.karte-summary-icon.red { background: #ffeaea; }

.karte-summary-label {
    font-size: 13px;
    font-weight: 800;
    color: #07195f;
    white-space: nowrap;
}

.karte-summary-number {
    font-size: 32px;
    line-height: 1;
    font-weight: 900;
    color: #07195f;
    margin-bottom: 24px;
    white-space: nowrap;
}

.karte-summary-number span {
    font-size: 18px;
    font-weight: 900;
}

.karte-progress {
    width: 92px;
    height: 10px;
    background: #dfe4ed;
    border-radius: 999px;
    overflow: hidden;
    margin-bottom: 14px;
}

.karte-progress-bar {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #06935f, #00b884);
}

.karte-summary-small {
    font-size: 13px;
    font-weight: 800;
    color: #07195f;
    margin-bottom: 5px;
    white-space: nowrap;
}

.karte-summary-small strong {
    color: #e60012;
    font-weight: 900;
}

.karte-summary-note {
    font-size: 12px;
    font-weight: 800;
    color: #4b5a8a;
}

.karte-summary-gain {
    font-size: 14px;
    font-weight: 900;
    color: #059669;
    white-space: nowrap;
}

.karte-summary-button {
    margin-top: auto;
    height: 34px;
    min-width: 98px;
    padding: 0 14px;
    border: 1px solid #8db6ff;
    border-radius: 7px;
    color: #0057ff;
    background: #ffffff;
    font-size: 13px;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.karte-current-title {
    font-size: 22px;
    line-height: 1.2;
    font-weight: 900;
    color: #07195f;
    margin-bottom: auto;
    white-space: nowrap;
}

@media (max-width: 1200px) {
    .karte-header-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .karte-student-profile {
        grid-template-columns: 1fr;
        justify-items: center;
        text-align: center;
    }

    .karte-student-title-row {
        justify-content: center;
        flex-wrap: wrap;
        padding-right: 0;
    }

    .karte-student-info-grid,
    .karte-bottom-info {
        grid-template-columns: 90px 1fr;
    }

    .karte-summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }

    .karte-summary-card:nth-child(2) {
        border-right: none;
    }
}
</style>

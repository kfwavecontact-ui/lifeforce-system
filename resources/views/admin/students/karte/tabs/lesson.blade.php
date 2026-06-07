<div class="lesson-top-grid lesson-top-grid-merged">
    @include('admin.students.karte.partials.lesson.upcoming')
    @include('admin.students.karte.partials.lesson.notes')
</div>

@include('admin.students.karte.partials.lesson.history')

@include('admin.students.karte.partials.lesson.alerts')

<div class="lesson-bottom-grid">
    @include('admin.students.karte.partials.lesson.makeup')
    @include('admin.students.karte.partials.lesson.attendance_summary')
    @include('admin.students.karte.partials.lesson.absence_reasons')
</div>
@php
    $planId = request('plan', 1);
@endphp

<div class="plan-grid">
    @include('admin.students.karte.partials.learning_plan.plan_list')
    @include('admin.students.karte.partials.learning_plan.plan_detail')
</div>
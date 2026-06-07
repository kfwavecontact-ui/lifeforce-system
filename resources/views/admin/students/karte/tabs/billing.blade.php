<div class="billing-tab">

    @include('admin.students.karte.partials.billing.summary')

    @include('admin.students.karte.partials.billing.tuition_history')

    @include('admin.students.karte.partials.billing.shop_history')

    @include('admin.students.karte.partials.billing.event_history')

    @include('admin.students.karte.partials.billing.onetime_history')

    @include('admin.students.karte.partials.billing.refund_history')

    <div class="billing-two-grid">
        @include('admin.students.karte.partials.billing.invoice_pdfs')
        @include('admin.students.karte.partials.billing.receipt_pdfs')
    </div>

</div>
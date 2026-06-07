<div class="event-tab">

    @include('admin.students.karte.partials.event.event_summary')

    <div class="event-main-grid">

        @include('admin.students.karte.partials.event.upcoming_events')

        @include('admin.students.karte.partials.event.recommended_events')

        @include('admin.students.karte.partials.event.event_histories')

    </div>

    <div class="event-bottom-grid">

        @include('admin.students.karte.partials.event.event_comments')

        @include('admin.students.karte.partials.event.event_photos')

    </div>

</div>
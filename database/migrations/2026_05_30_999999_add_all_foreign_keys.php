<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_user_roles_user_id_users_id_e1b0dc50')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('user_roles', function (Blueprint $table) {
            $table->foreign('role_id', 'fk_user_roles_role_id_roles_id_432a689d')
                ->references('id')
                ->on('roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->foreign('role_id', 'fk_role_permissions_role_id_roles_id_ee90dfa6')
                ->references('id')
                ->on('roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->foreign('permission_id', 'fk_role_permissions_permission_id_permissions_id_2e7b52df')
                ->references('id')
                ->on('permissions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('schools', function (Blueprint $table) {
            $table->foreign('area_id', 'fk_schools_area_id_areas_id_2f486c54')
                ->references('id')
                ->on('areas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('school_users', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_school_users_school_id_schools_id_022272ee')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('school_users', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_school_users_user_id_users_id_b11b50c4')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('school_users', function (Blueprint $table) {
            $table->foreign('role_id', 'fk_school_users_role_id_roles_id_05918771')
                ->references('id')
                ->on('roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('students', function (Blueprint $table) {
            $table->foreign('grade_id', 'fk_students_grade_id_grades_id_91b65081')
                ->references('id')
                ->on('grades')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('students', function (Blueprint $table) {
            $table->foreign('enrollment_status_id', 'fk_students_enrollment_status_id_enrollment_statuses_i_590955a9')
                ->references('id')
                ->on('enrollment_statuses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('students', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_students_school_id_schools_id_66a9d4e9')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('students', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_students_user_id_users_id_bf48e82b')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('parents', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_parents_user_id_users_id_f3b70e41')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('parent_students', function (Blueprint $table) {
            $table->foreign('parent_id', 'fk_parent_students_parent_id_parents_id_d2988e4e')
                ->references('id')
                ->on('parents')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('parent_students', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_parent_students_student_id_students_id_d7ec12e6')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreign('employment_type_id', 'fk_teachers_employment_type_id_employment_types_id_01bef89c')
                ->references('id')
                ->on('employment_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreign('teacher_status_id', 'fk_teachers_teacher_status_id_teacher_statuses_id_55d9f10f')
                ->references('id')
                ->on('teacher_statuses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_teachers_user_id_users_id_a45a4e01')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('course_prices', function (Blueprint $table) {
            $table->foreign('course_id', 'fk_course_prices_course_id_courses_id_4f70d196')
                ->references('id')
                ->on('courses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_course_contracts', function (Blueprint $table) {
            $table->foreign('course_price_id', 'fk_student_course_contracts_course_price_id_course_pri_13fbc323')
                ->references('id')
                ->on('course_prices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_course_contracts', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_student_course_contracts_student_id_students_id_c4588bc3')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_course_contracts', function (Blueprint $table) {
            $table->foreign('course_id', 'fk_student_course_contracts_course_id_courses_id_ad3af650')
                ->references('id')
                ->on('courses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_classrooms_school_id_schools_id_e9ce602f')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_calendar_events_school_id_schools_id_f4c09160')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreign('classroom_id', 'fk_calendar_events_classroom_id_classrooms_id_7d7a4e0f')
                ->references('id')
                ->on('classrooms')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreign('lesson_type_id', 'fk_calendar_events_lesson_type_id_lesson_types_id_224cf3c5')
                ->references('id')
                ->on('lesson_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreign('created_by_user_id', 'fk_calendar_events_created_by_user_id_users_id_b107e0ec')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_lesson_schedules_school_id_schools_id_22ace451')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->foreign('classroom_id', 'fk_lesson_schedules_classroom_id_classrooms_id_dcb6424c')
                ->references('id')
                ->on('classrooms')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->foreign('course_id', 'fk_lesson_schedules_course_id_courses_id_db7fd7f0')
                ->references('id')
                ->on('courses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->foreign('lesson_type_id', 'fk_lesson_schedules_lesson_type_id_lesson_types_id_1fdd7b5d')
                ->references('id')
                ->on('lesson_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->foreign('teacher_user_id', 'fk_lesson_schedules_teacher_user_id_users_id_0fece965')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->foreign('lesson_schedule_id', 'fk_lesson_sessions_lesson_schedule_id_lesson_schedules_4fc83426')
                ->references('id')
                ->on('lesson_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->foreign('calendar_event_id', 'fk_lesson_sessions_calendar_event_id_calendar_events_i_4c50ad9a')
                ->references('id')
                ->on('calendar_events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_lesson_sessions_school_id_schools_id_b3707ff3')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->foreign('classroom_id', 'fk_lesson_sessions_classroom_id_classrooms_id_2ad56d93')
                ->references('id')
                ->on('classrooms')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->foreign('course_id', 'fk_lesson_sessions_course_id_courses_id_7ffe8994')
                ->references('id')
                ->on('courses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->foreign('lesson_type_id', 'fk_lesson_sessions_lesson_type_id_lesson_types_id_7e950edc')
                ->references('id')
                ->on('lesson_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->foreign('teacher_user_id', 'fk_lesson_sessions_teacher_user_id_users_id_5a645046')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_lesson_reservations', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_student_lesson_reservations_student_id_students_id_1b45ba8c')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_lesson_reservations', function (Blueprint $table) {
            $table->foreign('lesson_session_id', 'fk_student_lesson_reservations_lesson_session_id_lesso_d6b37f7c')
                ->references('id')
                ->on('lesson_sessions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_lesson_reservations', function (Blueprint $table) {
            $table->foreign('original_reservation_id', 'fk_student_lesson_reservations_original_reservation_id_05e03218')
                ->references('id')
                ->on('student_lesson_reservations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('student_lesson_reservation_id', 'fk_attendances_student_lesson_reservation_id_student_l_e41b1849')
                ->references('id')
                ->on('student_lesson_reservations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_attendances_student_id_students_id_be738904')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('lesson_session_id', 'fk_attendances_lesson_session_id_lesson_sessions_id_80b4bad9')
                ->references('id')
                ->on('lesson_sessions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('recorded_by_user_id', 'fk_attendances_recorded_by_user_id_users_id_96d54ca7')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_notes', function (Blueprint $table) {
            $table->foreign('lesson_session_id', 'fk_lesson_notes_lesson_session_id_lesson_sessions_id_6e067c46')
                ->references('id')
                ->on('lesson_sessions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_notes', function (Blueprint $table) {
            $table->foreign('note_type_id', 'fk_lesson_notes_note_type_id_note_types_id_1c21de8f')
                ->references('id')
                ->on('note_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_notes', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_lesson_notes_user_id_users_id_ab0e54ee')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->foreign('student_lesson_reservation_id', 'fk_student_lesson_notes_student_lesson_reservation_id__5e64d2af')
                ->references('id')
                ->on('student_lesson_reservations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_student_lesson_notes_student_id_students_id_2207da6d')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->foreign('lesson_session_id', 'fk_student_lesson_notes_lesson_session_id_lesson_sessi_f8198ef0')
                ->references('id')
                ->on('lesson_sessions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->foreign('note_type_id', 'fk_student_lesson_notes_note_type_id_note_types_id_e426dbfd')
                ->references('id')
                ->on('note_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_student_lesson_notes_user_id_users_id_634013eb')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_learning_plans_student_id_students_id_a3ab6b2f')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->foreign('learning_plan_type_id', 'fk_learning_plans_learning_plan_type_id_learning_plan__aeb30271')
                ->references('id')
                ->on('learning_plan_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->foreign('manager_user_id', 'fk_learning_plans_manager_user_id_users_id_c21afea8')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->foreign('qualification_id', 'fk_learning_plans_qualification_id_qualifications_id_53407ea2')
                ->references('id')
                ->on('qualifications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_milestones', function (Blueprint $table) {
            $table->foreign('learning_plan_id', 'fk_learning_plan_milestones_learning_plan_id_learning__3dd304b7')
                ->references('id')
                ->on('learning_plans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_tasks', function (Blueprint $table) {
            $table->foreign('learning_plan_milestone_id', 'fk_learning_plan_tasks_learning_plan_milestone_id_lear_9affb27a')
                ->references('id')
                ->on('learning_plan_milestones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->foreign('learning_plan_id', 'fk_learning_plan_progress_logs_learning_plan_id_learni_68f6e617')
                ->references('id')
                ->on('learning_plans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->foreign('learning_plan_milestone_id', 'fk_learning_plan_progress_logs_learning_plan_milestone_e5c120b6')
                ->references('id')
                ->on('learning_plan_milestones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->foreign('learning_plan_task_id', 'fk_learning_plan_progress_logs_learning_plan_task_id_l_f8094b52')
                ->references('id')
                ->on('learning_plan_tasks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_learning_plan_progress_logs_student_id_students_id_559e6fad')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->foreign('recorded_by_user_id', 'fk_learning_plan_progress_logs_recorded_by_user_id_use_129e43cf')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->foreign('category_id', 'fk_learning_materials_category_id_learning_material_ca_70d0cf96')
                ->references('id')
                ->on('learning_material_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->foreign('created_by_user_id', 'fk_learning_materials_created_by_user_id_users_id_9f147f41')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_material_versions', function (Blueprint $table) {
            $table->foreign('learning_material_id', 'fk_learning_material_versions_learning_material_id_lea_a15fbe03')
                ->references('id')
                ->on('learning_materials')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_task_materials', function (Blueprint $table) {
            $table->foreign('learning_plan_task_id', 'fk_learning_plan_task_materials_learning_plan_task_id__6564a300')
                ->references('id')
                ->on('learning_plan_tasks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_plan_task_materials', function (Blueprint $table) {
            $table->foreign('learning_material_id', 'fk_learning_plan_task_materials_learning_material_id_l_edf1c49d')
                ->references('id')
                ->on('learning_materials')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_routines', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_learning_routines_student_id_students_id_912685d4')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_routines', function (Blueprint $table) {
            $table->foreign('routine_type_id', 'fk_learning_routines_routine_type_id_routine_types_id_0e02116c')
                ->references('id')
                ->on('routine_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_routines', function (Blueprint $table) {
            $table->foreign('manager_user_id', 'fk_learning_routines_manager_user_id_users_id_49661a5e')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_routine_logs', function (Blueprint $table) {
            $table->foreign('learning_routine_id', 'fk_learning_routine_logs_learning_routine_id_learning__9e7f4d39')
                ->references('id')
                ->on('learning_routines')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_routine_logs', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_learning_routine_logs_student_id_students_id_314d3150')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_invoices_student_id_students_id_d5c35069')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('payment_method_id', 'fk_invoices_payment_method_id_payment_methods_id_dbb0061b')
                ->references('id')
                ->on('payment_methods')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('invoice_id', 'fk_invoice_items_invoice_id_invoices_id_783b6c62')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('invoice_id', 'fk_payments_invoice_id_invoices_id_ad889fd3')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_payments_student_id_students_id_c8838332')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('payment_method_id', 'fk_payments_payment_method_id_payment_methods_id_ea1b1ca4')
                ->references('id')
                ->on('payment_methods')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('confirmed_by', 'fk_payments_confirmed_by_users_id_c5a240bd')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_discounts', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_student_discounts_student_id_students_id_bab092e9')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_discounts', function (Blueprint $table) {
            $table->foreign('discount_id', 'fk_student_discounts_discount_id_discounts_id_965d5220')
                ->references('id')
                ->on('discounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('notification_type_id', 'fk_notifications_notification_type_id_notification_typ_607f7f60')
                ->references('id')
                ->on('notification_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('created_by_user_id', 'fk_notifications_created_by_user_id_users_id_2b5a52aa')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->foreign('notification_id', 'fk_notification_recipients_notification_id_notificatio_4e6a104b')
                ->references('id')
                ->on('notifications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_notification_recipients_user_id_users_id_4f7f4557')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('line_message_logs', function (Blueprint $table) {
            $table->foreign('notification_id', 'fk_line_message_logs_notification_id_notifications_id_3945ecdd')
                ->references('id')
                ->on('notifications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('line_message_logs', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_line_message_logs_user_id_users_id_cc8759d8')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreign('notification_id', 'fk_email_logs_notification_id_notifications_id_d3cbfd2e')
                ->references('id')
                ->on('notifications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_email_logs_user_id_users_id_e1ab20a8')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('contact_type_id', 'fk_contacts_contact_type_id_contact_types_id_b306a72d')
                ->references('id')
                ->on('contact_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('contact_status_id', 'fk_contacts_contact_status_id_contact_statuses_id_e799fd11')
                ->references('id')
                ->on('contact_statuses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_contacts_student_id_students_id_e0dbb156')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('created_by_user_id', 'fk_contacts_created_by_user_id_users_id_95437985')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('assigned_user_id', 'fk_contacts_assigned_user_id_users_id_8ddff60f')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->foreign('contact_id', 'fk_contact_messages_contact_id_contacts_id_d72a2d57')
                ->references('id')
                ->on('contacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->foreign('sender_user_id', 'fk_contact_messages_sender_user_id_users_id_060667b6')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contact_attachments', function (Blueprint $table) {
            $table->foreign('contact_message_id', 'fk_contact_attachments_contact_message_id_contact_mess_be35c49c')
                ->references('id')
                ->on('contact_messages')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('contact_attachments', function (Blueprint $table) {
            $table->foreign('uploaded_by_user_id', 'fk_contact_attachments_uploaded_by_user_id_users_id_16725d64')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_audit_logs_user_id_users_id_c794126c')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('login_logs', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_login_logs_user_id_users_id_52eeec49')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('operation_logs', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_operation_logs_user_id_users_id_3ba6c660')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('file_upload_logs', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_file_upload_logs_user_id_users_id_7d58d135')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('school_settings', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_school_settings_school_id_schools_id_57cd2692')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('holidays', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_holidays_school_id_schools_id_1269e668')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('business_days', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_business_days_school_id_schools_id_135af064')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('learning_contents', function (Blueprint $table) {
            $table->foreign('default_completion_type_id', 'fk_learning_contents_default_completion_type_id_routin_ad3fc7ff')
                ->references('id')
                ->on('routine_completion_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_learning_content_settings', function (Blueprint $table) {
            $table->foreign('learning_content_id', 'fk_student_learning_content_settings_learning_content__f6b59709')
                ->references('id')
                ->on('learning_contents')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_routines', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_student_routines_student_id_students_id_88645b7f')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_routines', function (Blueprint $table) {
            $table->foreign('created_by', 'fk_student_routines_created_by_users_id_95885eb9')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->foreign('student_routine_id', 'fk_student_routine_items_student_routine_id_student_ro_e379563e')
                ->references('id')
                ->on('student_routines')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->foreign('learning_content_id', 'fk_student_routine_items_learning_content_id_learning__ddb75ede')
                ->references('id')
                ->on('learning_contents')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->foreign('completion_type_id', 'fk_student_routine_items_completion_type_id_routine_co_d180509f')
                ->references('id')
                ->on('routine_completion_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('daily_routine_statuses', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_daily_routine_statuses_student_id_students_id_2850c9ae')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('daily_routine_statuses', function (Blueprint $table) {
            $table->foreign('student_routine_item_id', 'fk_daily_routine_statuses_student_routine_item_id_stud_80276f99')
                ->references('id')
                ->on('student_routine_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('daily_routine_statuses', function (Blueprint $table) {
            $table->foreign('approved_by', 'fk_daily_routine_statuses_approved_by_users_id_0dee377c')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_study_sessions_student_id_students_id_da288e46')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->foreign('learning_content_id', 'fk_study_sessions_learning_content_id_learning_content_ef454f0e')
                ->references('id')
                ->on('learning_contents')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('study_results', function (Blueprint $table) {
            $table->foreign('study_session_id', 'fk_study_results_study_session_id_study_sessions_id_a3e77dc6')
                ->references('id')
                ->on('study_sessions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_point_transactions_student_id_students_id_bcca5afc')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_point_balances', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_student_point_balances_student_id_students_id_fdbbfd0d')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('reward_items', function (Blueprint $table) {
            $table->foreign('reward_category_id', 'fk_reward_items_reward_category_id_reward_categories_i_bd76f7ef')
                ->references('id')
                ->on('reward_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('reward_exchange_requests', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_reward_exchange_requests_student_id_students_id_e4db3fdc')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('reward_exchange_requests', function (Blueprint $table) {
            $table->foreign('reward_item_id', 'fk_reward_exchange_requests_reward_item_id_reward_item_c40a6a65')
                ->references('id')
                ->on('reward_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('reward_exchange_requests', function (Blueprint $table) {
            $table->foreign('handled_by', 'fk_reward_exchange_requests_handled_by_users_id_7bbd4295')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badges', function (Blueprint $table) {
            $table->foreign('badge_category_id', 'fk_badges_badge_category_id_badge_categories_id_6c442887')
                ->references('id')
                ->on('badge_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badge_requirements', function (Blueprint $table) {
            $table->foreign('badge_id', 'fk_badge_requirements_badge_id_badges_id_349c513f')
                ->references('id')
                ->on('badges')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badge_rewards', function (Blueprint $table) {
            $table->foreign('badge_id', 'fk_badge_rewards_badge_id_badges_id_38007840')
                ->references('id')
                ->on('badges')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badge_attempt_reservations', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_badge_attempt_reservations_student_id_students_id_fecce8bb')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badge_attempt_reservations', function (Blueprint $table) {
            $table->foreign('badge_id', 'fk_badge_attempt_reservations_badge_id_badges_id_dc905566')
                ->references('id')
                ->on('badges')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badge_attempt_reservations', function (Blueprint $table) {
            $table->foreign('approved_teacher_id', 'fk_badge_attempt_reservations_approved_teacher_id_user_09239e48')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badge_challenge_logs', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_badge_challenge_logs_student_id_students_id_6ab722d7')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badge_challenge_logs', function (Blueprint $table) {
            $table->foreign('badge_id', 'fk_badge_challenge_logs_badge_id_badges_id_78ea6b1a')
                ->references('id')
                ->on('badges')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('badge_challenge_logs', function (Blueprint $table) {
            $table->foreign('checked_teacher_id', 'fk_badge_challenge_logs_checked_teacher_id_users_id_938b2df2')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_badges', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_student_badges_student_id_students_id_0e369be0')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_badges', function (Blueprint $table) {
            $table->foreign('badge_id', 'fk_student_badges_badge_id_badges_id_9e746ba3')
                ->references('id')
                ->on('badges')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_titles', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_student_titles_student_id_students_id_e2e4953b')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('student_titles', function (Blueprint $table) {
            $table->foreign('title_id', 'fk_student_titles_title_id_titles_id_83cbf449')
                ->references('id')
                ->on('titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('events', function (Blueprint $table) {
            $table->foreign('event_category_id', 'fk_events_event_category_id_event_categories_id_e35ea7c8')
                ->references('id')
                ->on('event_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('events', function (Blueprint $table) {
            $table->foreign('event_status_id', 'fk_events_event_status_id_event_statuses_id_107bfb76')
                ->references('id')
                ->on('event_statuses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('events', function (Blueprint $table) {
            $table->foreign('organizer_user_id', 'fk_events_organizer_user_id_users_id_44c995d9')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_schedules', function (Blueprint $table) {
            $table->foreign('event_id', 'fk_event_schedules_event_id_events_id_28dbd7ae')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_targets', function (Blueprint $table) {
            $table->foreign('event_id', 'fk_event_targets_event_id_events_id_a8910ead')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_targets', function (Blueprint $table) {
            $table->foreign('area_id', 'fk_event_targets_area_id_areas_id_90c8e966')
                ->references('id')
                ->on('areas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_targets', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_event_targets_school_id_schools_id_b3dbd113')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_locations', function (Blueprint $table) {
            $table->foreign('event_schedule_id', 'fk_event_locations_event_schedule_id_event_schedules_i_bf9d1e8e')
                ->references('id')
                ->on('event_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_locations', function (Blueprint $table) {
            $table->foreign('school_id', 'fk_event_locations_school_id_schools_id_444e91df')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_prices', function (Blueprint $table) {
            $table->foreign('event_id', 'fk_event_prices_event_id_events_id_a86b318e')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_applications', function (Blueprint $table) {
            $table->foreign('event_id', 'fk_event_applications_event_id_events_id_f923550b')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_applications', function (Blueprint $table) {
            $table->foreign('event_schedule_id', 'fk_event_applications_event_schedule_id_event_schedule_3a138bf2')
                ->references('id')
                ->on('event_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_applications', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_event_applications_student_id_students_id_c1552965')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_applications', function (Blueprint $table) {
            $table->foreign('applicant_user_id', 'fk_event_applications_applicant_user_id_users_id_6953ea88')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_payments', function (Blueprint $table) {
            $table->foreign('event_application_id', 'fk_event_payments_event_application_id_event_applicati_0ad39915')
                ->references('id')
                ->on('event_applications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_refunds', function (Blueprint $table) {
            $table->foreign('event_payment_id', 'fk_event_refunds_event_payment_id_event_payments_id_6a83972b')
                ->references('id')
                ->on('event_payments')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_refunds', function (Blueprint $table) {
            $table->foreign('handled_by', 'fk_event_refunds_handled_by_users_id_92bd212b')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_participants', function (Blueprint $table) {
            $table->foreign('event_application_id', 'fk_event_participants_event_application_id_event_appli_933bf278')
                ->references('id')
                ->on('event_applications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_participants', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_event_participants_student_id_students_id_49cfe7d9')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_waitlists', function (Blueprint $table) {
            $table->foreign('event_schedule_id', 'fk_event_waitlists_event_schedule_id_event_schedules_i_cee757c0')
                ->references('id')
                ->on('event_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_waitlists', function (Blueprint $table) {
            $table->foreign('event_application_id', 'fk_event_waitlists_event_application_id_event_applicat_dcb37778')
                ->references('id')
                ->on('event_applications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_waitlists', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_event_waitlists_student_id_students_id_56bbc390')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_notifications', function (Blueprint $table) {
            $table->foreign('event_id', 'fk_event_notifications_event_id_events_id_50ef0b91')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_notifications', function (Blueprint $table) {
            $table->foreign('event_schedule_id', 'fk_event_notifications_event_schedule_id_event_schedul_549bc81b')
                ->references('id')
                ->on('event_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_notifications', function (Blueprint $table) {
            $table->foreign('event_application_id', 'fk_event_notifications_event_application_id_event_appl_fa858505')
                ->references('id')
                ->on('event_applications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->foreign('event_id', 'fk_event_result_records_event_id_events_id_77b44fed')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->foreign('event_schedule_id', 'fk_event_result_records_event_schedule_id_event_schedu_6b4f6825')
                ->references('id')
                ->on('event_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->foreign('event_application_id', 'fk_event_result_records_event_application_id_event_app_66fefadb')
                ->references('id')
                ->on('event_applications')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_event_result_records_student_id_students_id_ef1d0575')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->foreign('recorded_by', 'fk_event_result_records_recorded_by_users_id_ff2157a3')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_rewards', function (Blueprint $table) {
            $table->foreign('event_id', 'fk_event_rewards_event_id_events_id_9e94e07b')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_rewards', function (Blueprint $table) {
            $table->foreign('badge_id', 'fk_event_rewards_badge_id_badges_id_24d4de84')
                ->references('id')
                ->on('badges')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_rewards', function (Blueprint $table) {
            $table->foreign('title_id', 'fk_event_rewards_title_id_titles_id_4c54729c')
                ->references('id')
                ->on('titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->foreign('event_id', 'fk_event_attendance_rewards_event_id_events_id_83ce38b1')
                ->references('id')
                ->on('events')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->foreign('event_schedule_id', 'fk_event_attendance_rewards_event_schedule_id_event_sc_9267427e')
                ->references('id')
                ->on('event_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->foreign('event_participant_id', 'fk_event_attendance_rewards_event_participant_id_event_b5137ce5')
                ->references('id')
                ->on('event_participants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->foreign('student_id', 'fk_event_attendance_rewards_student_id_students_id_e6a588d7')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->foreign('event_reward_id', 'fk_event_attendance_rewards_event_reward_id_event_rewa_c4162f0f')
                ->references('id')
                ->on('event_rewards')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->foreign('badge_id', 'fk_event_attendance_rewards_badge_id_badges_id_a50f9764')
                ->references('id')
                ->on('badges')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->foreign('title_id', 'fk_event_attendance_rewards_title_id_titles_id_095d0984')
                ->references('id')
                ->on('titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->foreign('granted_by', 'fk_event_attendance_rewards_granted_by_users_id_f9b8ae7b')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_staff_assignments', function (Blueprint $table) {
            $table->foreign('event_schedule_id', 'fk_event_staff_assignments_event_schedule_id_event_sch_9c2d2436')
                ->references('id')
                ->on('event_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_staff_assignments', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_event_staff_assignments_user_id_users_id_91b00e4c')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_checkins', function (Blueprint $table) {
            $table->foreign('event_participant_id', 'fk_event_checkins_event_participant_id_event_participa_13092b75')
                ->references('id')
                ->on('event_participants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('event_checkins', function (Blueprint $table) {
            $table->foreign('checked_in_by', 'fk_event_checkins_checked_in_by_users_id_85525926')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_products', function (Blueprint $table) {
            $table->foreign('shop_category_id', 'fk_shop_products_shop_category_id_shop_categories_id_9f3ac4d3')
                ->references('id')
                ->on('shop_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_product_images', function (Blueprint $table) {
            $table->foreign('shop_product_id', 'fk_shop_product_images_shop_product_id_shop_products_i_2628094c')
                ->references('id')
                ->on('shop_products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_product_stocks', function (Blueprint $table) {
            $table->foreign('shop_product_id', 'fk_shop_product_stocks_shop_product_id_shop_products_i_c81b9b05')
                ->references('id')
                ->on('shop_products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_carts', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_shop_carts_user_id_users_id_4a76678d')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_cart_items', function (Blueprint $table) {
            $table->foreign('shop_cart_id', 'fk_shop_cart_items_shop_cart_id_shop_carts_id_a3bbaa06')
                ->references('id')
                ->on('shop_carts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_cart_items', function (Blueprint $table) {
            $table->foreign('shop_product_id', 'fk_shop_cart_items_shop_product_id_shop_products_id_a2be3c27')
                ->references('id')
                ->on('shop_products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_shop_orders_user_id_users_id_ea69b16f')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->foreign('shop_order_id', 'fk_shop_order_items_shop_order_id_shop_orders_id_42e14e11')
                ->references('id')
                ->on('shop_orders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->foreign('shop_product_id', 'fk_shop_order_items_shop_product_id_shop_products_id_a3ee9c4c')
                ->references('id')
                ->on('shop_products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_payments', function (Blueprint $table) {
            $table->foreign('shop_order_id', 'fk_shop_payments_shop_order_id_shop_orders_id_94adaf33')
                ->references('id')
                ->on('shop_orders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_payments', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_shop_payments_user_id_users_id_6ffd2c79')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_payment_webhooks', function (Blueprint $table) {
            $table->foreign('shop_payment_id', 'fk_shop_payment_webhooks_shop_payment_id_shop_payments_32315c76')
                ->references('id')
                ->on('shop_payments')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_pickups', function (Blueprint $table) {
            $table->foreign('shop_order_id', 'fk_shop_pickups_shop_order_id_shop_orders_id_53223e1f')
                ->references('id')
                ->on('shop_orders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_pickups', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_shop_pickups_user_id_users_id_3d2a0455')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_pickups', function (Blueprint $table) {
            $table->foreign('pickup_school_id', 'fk_shop_pickups_pickup_school_id_schools_id_6d2c24bf')
                ->references('id')
                ->on('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_pickups', function (Blueprint $table) {
            $table->foreign('handled_by', 'fk_shop_pickups_handled_by_users_id_d24139d0')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('shop_product_stocks', function (Blueprint $table) {
            $table->foreign('updated_by', 'fk_shop_product_stocks_updated_by_users_id_5388ed7d')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
        Schema::table('lesson_makeups', function (Blueprint $table) {
            $table->foreign('student_id')
                ->references('id')
                ->on('students');
        });

        Schema::table('lesson_makeups', function (Blueprint $table) {
            $table->foreign('attendance_id')
                ->references('id')
                ->on('attendances');
        });

        Schema::table('lesson_makeups', function (Blueprint $table) {
            $table->foreign('original_lesson_session_id')
                ->references('id')
                ->on('lesson_sessions');
        });

        Schema::table('lesson_makeups', function (Blueprint $table) {
            $table->foreign('makeup_lesson_session_id')
                ->references('id')
                ->on('lesson_sessions');
        });

        Schema::table('lesson_makeups', function (Blueprint $table) {
            $table->foreign('requested_by_user_id')
                ->references('id')
                ->on('users');
        });

        Schema::table('lesson_makeups', function (Blueprint $table) {
            $table->foreign('approved_by_user_id')
                ->references('id')
                ->on('users');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_product_stocks', function (Blueprint $table) {
            $table->dropForeign('fk_shop_product_stocks_updated_by_users_id_5388ed7d');
        });
        Schema::table('shop_pickups', function (Blueprint $table) {
            $table->dropForeign('fk_shop_pickups_handled_by_users_id_d24139d0');
        });
        Schema::table('shop_pickups', function (Blueprint $table) {
            $table->dropForeign('fk_shop_pickups_pickup_school_id_schools_id_6d2c24bf');
        });
        Schema::table('shop_pickups', function (Blueprint $table) {
            $table->dropForeign('fk_shop_pickups_user_id_users_id_3d2a0455');
        });
        Schema::table('shop_pickups', function (Blueprint $table) {
            $table->dropForeign('fk_shop_pickups_shop_order_id_shop_orders_id_53223e1f');
        });
        Schema::table('shop_payment_webhooks', function (Blueprint $table) {
            $table->dropForeign('fk_shop_payment_webhooks_shop_payment_id_shop_payments_32315c76');
        });
        Schema::table('shop_payments', function (Blueprint $table) {
            $table->dropForeign('fk_shop_payments_user_id_users_id_6ffd2c79');
        });
        Schema::table('shop_payments', function (Blueprint $table) {
            $table->dropForeign('fk_shop_payments_shop_order_id_shop_orders_id_94adaf33');
        });
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropForeign('fk_shop_order_items_shop_product_id_shop_products_id_a3ee9c4c');
        });
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropForeign('fk_shop_order_items_shop_order_id_shop_orders_id_42e14e11');
        });
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropForeign('fk_shop_orders_user_id_users_id_ea69b16f');
        });
        Schema::table('shop_cart_items', function (Blueprint $table) {
            $table->dropForeign('fk_shop_cart_items_shop_product_id_shop_products_id_a2be3c27');
        });
        Schema::table('shop_cart_items', function (Blueprint $table) {
            $table->dropForeign('fk_shop_cart_items_shop_cart_id_shop_carts_id_a3bbaa06');
        });
        Schema::table('shop_carts', function (Blueprint $table) {
            $table->dropForeign('fk_shop_carts_user_id_users_id_4a76678d');
        });
        Schema::table('shop_product_stocks', function (Blueprint $table) {
            $table->dropForeign('fk_shop_product_stocks_shop_product_id_shop_products_i_c81b9b05');
        });
        Schema::table('shop_product_images', function (Blueprint $table) {
            $table->dropForeign('fk_shop_product_images_shop_product_id_shop_products_i_2628094c');
        });
        Schema::table('shop_products', function (Blueprint $table) {
            $table->dropForeign('fk_shop_products_shop_category_id_shop_categories_id_9f3ac4d3');
        });
        Schema::table('event_checkins', function (Blueprint $table) {
            $table->dropForeign('fk_event_checkins_checked_in_by_users_id_85525926');
        });
        Schema::table('event_checkins', function (Blueprint $table) {
            $table->dropForeign('fk_event_checkins_event_participant_id_event_participa_13092b75');
        });
        Schema::table('event_staff_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_event_staff_assignments_user_id_users_id_91b00e4c');
        });
        Schema::table('event_staff_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_event_staff_assignments_event_schedule_id_event_sch_9c2d2436');
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_attendance_rewards_granted_by_users_id_f9b8ae7b');
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_attendance_rewards_title_id_titles_id_095d0984');
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_attendance_rewards_badge_id_badges_id_a50f9764');
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_attendance_rewards_event_reward_id_event_rewa_c4162f0f');
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_attendance_rewards_student_id_students_id_e6a588d7');
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_attendance_rewards_event_participant_id_event_b5137ce5');
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_attendance_rewards_event_schedule_id_event_sc_9267427e');
        });
        Schema::table('event_attendance_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_attendance_rewards_event_id_events_id_83ce38b1');
        });
        Schema::table('event_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_rewards_title_id_titles_id_4c54729c');
        });
        Schema::table('event_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_rewards_badge_id_badges_id_24d4de84');
        });
        Schema::table('event_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_event_rewards_event_id_events_id_9e94e07b');
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->dropForeign('fk_event_result_records_recorded_by_users_id_ff2157a3');
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->dropForeign('fk_event_result_records_student_id_students_id_ef1d0575');
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->dropForeign('fk_event_result_records_event_application_id_event_app_66fefadb');
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->dropForeign('fk_event_result_records_event_schedule_id_event_schedu_6b4f6825');
        });
        Schema::table('event_result_records', function (Blueprint $table) {
            $table->dropForeign('fk_event_result_records_event_id_events_id_77b44fed');
        });
        Schema::table('event_notifications', function (Blueprint $table) {
            $table->dropForeign('fk_event_notifications_event_application_id_event_appl_fa858505');
        });
        Schema::table('event_notifications', function (Blueprint $table) {
            $table->dropForeign('fk_event_notifications_event_schedule_id_event_schedul_549bc81b');
        });
        Schema::table('event_notifications', function (Blueprint $table) {
            $table->dropForeign('fk_event_notifications_event_id_events_id_50ef0b91');
        });
        Schema::table('event_waitlists', function (Blueprint $table) {
            $table->dropForeign('fk_event_waitlists_student_id_students_id_56bbc390');
        });
        Schema::table('event_waitlists', function (Blueprint $table) {
            $table->dropForeign('fk_event_waitlists_event_application_id_event_applicat_dcb37778');
        });
        Schema::table('event_waitlists', function (Blueprint $table) {
            $table->dropForeign('fk_event_waitlists_event_schedule_id_event_schedules_i_cee757c0');
        });
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropForeign('fk_event_participants_student_id_students_id_49cfe7d9');
        });
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropForeign('fk_event_participants_event_application_id_event_appli_933bf278');
        });
        Schema::table('event_refunds', function (Blueprint $table) {
            $table->dropForeign('fk_event_refunds_handled_by_users_id_92bd212b');
        });
        Schema::table('event_refunds', function (Blueprint $table) {
            $table->dropForeign('fk_event_refunds_event_payment_id_event_payments_id_6a83972b');
        });
        Schema::table('event_payments', function (Blueprint $table) {
            $table->dropForeign('fk_event_payments_event_application_id_event_applicati_0ad39915');
        });
        Schema::table('event_applications', function (Blueprint $table) {
            $table->dropForeign('fk_event_applications_applicant_user_id_users_id_6953ea88');
        });
        Schema::table('event_applications', function (Blueprint $table) {
            $table->dropForeign('fk_event_applications_student_id_students_id_c1552965');
        });
        Schema::table('event_applications', function (Blueprint $table) {
            $table->dropForeign('fk_event_applications_event_schedule_id_event_schedule_3a138bf2');
        });
        Schema::table('event_applications', function (Blueprint $table) {
            $table->dropForeign('fk_event_applications_event_id_events_id_f923550b');
        });
        Schema::table('event_prices', function (Blueprint $table) {
            $table->dropForeign('fk_event_prices_event_id_events_id_a86b318e');
        });
        Schema::table('event_locations', function (Blueprint $table) {
            $table->dropForeign('fk_event_locations_school_id_schools_id_444e91df');
        });
        Schema::table('event_locations', function (Blueprint $table) {
            $table->dropForeign('fk_event_locations_event_schedule_id_event_schedules_i_bf9d1e8e');
        });
        Schema::table('event_targets', function (Blueprint $table) {
            $table->dropForeign('fk_event_targets_school_id_schools_id_b3dbd113');
        });
        Schema::table('event_targets', function (Blueprint $table) {
            $table->dropForeign('fk_event_targets_area_id_areas_id_90c8e966');
        });
        Schema::table('event_targets', function (Blueprint $table) {
            $table->dropForeign('fk_event_targets_event_id_events_id_a8910ead');
        });
        Schema::table('event_schedules', function (Blueprint $table) {
            $table->dropForeign('fk_event_schedules_event_id_events_id_28dbd7ae');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign('fk_events_organizer_user_id_users_id_44c995d9');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign('fk_events_event_status_id_event_statuses_id_107bfb76');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign('fk_events_event_category_id_event_categories_id_e35ea7c8');
        });
        Schema::table('student_titles', function (Blueprint $table) {
            $table->dropForeign('fk_student_titles_title_id_titles_id_83cbf449');
        });
        Schema::table('student_titles', function (Blueprint $table) {
            $table->dropForeign('fk_student_titles_student_id_students_id_e2e4953b');
        });
        Schema::table('student_badges', function (Blueprint $table) {
            $table->dropForeign('fk_student_badges_badge_id_badges_id_9e746ba3');
        });
        Schema::table('student_badges', function (Blueprint $table) {
            $table->dropForeign('fk_student_badges_student_id_students_id_0e369be0');
        });
        Schema::table('badge_challenge_logs', function (Blueprint $table) {
            $table->dropForeign('fk_badge_challenge_logs_checked_teacher_id_users_id_938b2df2');
        });
        Schema::table('badge_challenge_logs', function (Blueprint $table) {
            $table->dropForeign('fk_badge_challenge_logs_badge_id_badges_id_78ea6b1a');
        });
        Schema::table('badge_challenge_logs', function (Blueprint $table) {
            $table->dropForeign('fk_badge_challenge_logs_student_id_students_id_6ab722d7');
        });
        Schema::table('badge_attempt_reservations', function (Blueprint $table) {
            $table->dropForeign('fk_badge_attempt_reservations_approved_teacher_id_user_09239e48');
        });
        Schema::table('badge_attempt_reservations', function (Blueprint $table) {
            $table->dropForeign('fk_badge_attempt_reservations_badge_id_badges_id_dc905566');
        });
        Schema::table('badge_attempt_reservations', function (Blueprint $table) {
            $table->dropForeign('fk_badge_attempt_reservations_student_id_students_id_fecce8bb');
        });
        Schema::table('badge_rewards', function (Blueprint $table) {
            $table->dropForeign('fk_badge_rewards_badge_id_badges_id_38007840');
        });
        Schema::table('badge_requirements', function (Blueprint $table) {
            $table->dropForeign('fk_badge_requirements_badge_id_badges_id_349c513f');
        });
        Schema::table('badges', function (Blueprint $table) {
            $table->dropForeign('fk_badges_badge_category_id_badge_categories_id_6c442887');
        });
        Schema::table('reward_exchange_requests', function (Blueprint $table) {
            $table->dropForeign('fk_reward_exchange_requests_handled_by_users_id_7bbd4295');
        });
        Schema::table('reward_exchange_requests', function (Blueprint $table) {
            $table->dropForeign('fk_reward_exchange_requests_reward_item_id_reward_item_c40a6a65');
        });
        Schema::table('reward_exchange_requests', function (Blueprint $table) {
            $table->dropForeign('fk_reward_exchange_requests_student_id_students_id_e4db3fdc');
        });
        Schema::table('reward_items', function (Blueprint $table) {
            $table->dropForeign('fk_reward_items_reward_category_id_reward_categories_i_bd76f7ef');
        });
        Schema::table('student_point_balances', function (Blueprint $table) {
            $table->dropForeign('fk_student_point_balances_student_id_students_id_fdbbfd0d');
        });
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropForeign('fk_point_transactions_student_id_students_id_bcca5afc');
        });
        Schema::table('study_results', function (Blueprint $table) {
            $table->dropForeign('fk_study_results_study_session_id_study_sessions_id_a3e77dc6');
        });
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_study_sessions_learning_content_id_learning_content_ef454f0e');
        });
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_study_sessions_student_id_students_id_da288e46');
        });
        Schema::table('daily_routine_statuses', function (Blueprint $table) {
            $table->dropForeign('fk_daily_routine_statuses_approved_by_users_id_0dee377c');
        });
        Schema::table('daily_routine_statuses', function (Blueprint $table) {
            $table->dropForeign('fk_daily_routine_statuses_student_routine_item_id_stud_80276f99');
        });
        Schema::table('daily_routine_statuses', function (Blueprint $table) {
            $table->dropForeign('fk_daily_routine_statuses_student_id_students_id_2850c9ae');
        });
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->dropForeign('fk_student_routine_items_completion_type_id_routine_co_d180509f');
        });
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->dropForeign('fk_student_routine_items_learning_content_id_learning__ddb75ede');
        });
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->dropForeign('fk_student_routine_items_student_routine_id_student_ro_e379563e');
        });
        Schema::table('student_routines', function (Blueprint $table) {
            $table->dropForeign('fk_student_routines_created_by_users_id_95885eb9');
        });
        Schema::table('student_routines', function (Blueprint $table) {
            $table->dropForeign('fk_student_routines_student_id_students_id_88645b7f');
        });
        Schema::table('student_learning_content_settings', function (Blueprint $table) {
            $table->dropForeign('fk_student_learning_content_settings_learning_content__f6b59709');
        });
        Schema::table('learning_contents', function (Blueprint $table) {
            $table->dropForeign('fk_learning_contents_default_completion_type_id_routin_ad3fc7ff');
        });
        Schema::table('business_days', function (Blueprint $table) {
            $table->dropForeign('fk_business_days_school_id_schools_id_135af064');
        });
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropForeign('fk_holidays_school_id_schools_id_1269e668');
        });
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropForeign('fk_school_settings_school_id_schools_id_57cd2692');
        });
        Schema::table('file_upload_logs', function (Blueprint $table) {
            $table->dropForeign('fk_file_upload_logs_user_id_users_id_7d58d135');
        });
        Schema::table('operation_logs', function (Blueprint $table) {
            $table->dropForeign('fk_operation_logs_user_id_users_id_3ba6c660');
        });
        Schema::table('login_logs', function (Blueprint $table) {
            $table->dropForeign('fk_login_logs_user_id_users_id_52eeec49');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign('fk_audit_logs_user_id_users_id_c794126c');
        });
        Schema::table('contact_attachments', function (Blueprint $table) {
            $table->dropForeign('fk_contact_attachments_uploaded_by_user_id_users_id_16725d64');
        });
        Schema::table('contact_attachments', function (Blueprint $table) {
            $table->dropForeign('fk_contact_attachments_contact_message_id_contact_mess_be35c49c');
        });
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign('fk_contact_messages_sender_user_id_users_id_060667b6');
        });
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign('fk_contact_messages_contact_id_contacts_id_d72a2d57');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign('fk_contacts_assigned_user_id_users_id_8ddff60f');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign('fk_contacts_created_by_user_id_users_id_95437985');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign('fk_contacts_student_id_students_id_e0dbb156');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign('fk_contacts_contact_status_id_contact_statuses_id_e799fd11');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign('fk_contacts_contact_type_id_contact_types_id_b306a72d');
        });
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign('fk_email_logs_user_id_users_id_e1ab20a8');
        });
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign('fk_email_logs_notification_id_notifications_id_d3cbfd2e');
        });
        Schema::table('line_message_logs', function (Blueprint $table) {
            $table->dropForeign('fk_line_message_logs_user_id_users_id_cc8759d8');
        });
        Schema::table('line_message_logs', function (Blueprint $table) {
            $table->dropForeign('fk_line_message_logs_notification_id_notifications_id_3945ecdd');
        });
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->dropForeign('fk_notification_recipients_user_id_users_id_4f7f4557');
        });
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->dropForeign('fk_notification_recipients_notification_id_notificatio_4e6a104b');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign('fk_notifications_created_by_user_id_users_id_2b5a52aa');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign('fk_notifications_notification_type_id_notification_typ_607f7f60');
        });
        Schema::table('student_discounts', function (Blueprint $table) {
            $table->dropForeign('fk_student_discounts_discount_id_discounts_id_965d5220');
        });
        Schema::table('student_discounts', function (Blueprint $table) {
            $table->dropForeign('fk_student_discounts_student_id_students_id_bab092e9');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('fk_payments_confirmed_by_users_id_c5a240bd');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('fk_payments_payment_method_id_payment_methods_id_ea1b1ca4');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('fk_payments_student_id_students_id_c8838332');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('fk_payments_invoice_id_invoices_id_ad889fd3');
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign('fk_invoice_items_invoice_id_invoices_id_783b6c62');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign('fk_invoices_payment_method_id_payment_methods_id_dbb0061b');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign('fk_invoices_student_id_students_id_d5c35069');
        });
        Schema::table('learning_routine_logs', function (Blueprint $table) {
            $table->dropForeign('fk_learning_routine_logs_student_id_students_id_314d3150');
        });
        Schema::table('learning_routine_logs', function (Blueprint $table) {
            $table->dropForeign('fk_learning_routine_logs_learning_routine_id_learning__9e7f4d39');
        });
        Schema::table('learning_routines', function (Blueprint $table) {
            $table->dropForeign('fk_learning_routines_manager_user_id_users_id_49661a5e');
        });
        Schema::table('learning_routines', function (Blueprint $table) {
            $table->dropForeign('fk_learning_routines_routine_type_id_routine_types_id_0e02116c');
        });
        Schema::table('learning_routines', function (Blueprint $table) {
            $table->dropForeign('fk_learning_routines_student_id_students_id_912685d4');
        });
        Schema::table('learning_plan_task_materials', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_task_materials_learning_material_id_l_edf1c49d');
        });
        Schema::table('learning_plan_task_materials', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_task_materials_learning_plan_task_id__6564a300');
        });
        Schema::table('learning_material_versions', function (Blueprint $table) {
            $table->dropForeign('fk_learning_material_versions_learning_material_id_lea_a15fbe03');
        });
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->dropForeign('fk_learning_materials_created_by_user_id_users_id_9f147f41');
        });
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->dropForeign('fk_learning_materials_category_id_learning_material_ca_70d0cf96');
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_progress_logs_recorded_by_user_id_use_129e43cf');
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_progress_logs_student_id_students_id_559e6fad');
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_progress_logs_learning_plan_task_id_l_f8094b52');
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_progress_logs_learning_plan_milestone_e5c120b6');
        });
        Schema::table('learning_plan_progress_logs', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_progress_logs_learning_plan_id_learni_68f6e617');
        });
        Schema::table('learning_plan_tasks', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_tasks_learning_plan_milestone_id_lear_9affb27a');
        });
        Schema::table('learning_plan_milestones', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plan_milestones_learning_plan_id_learning__3dd304b7');
        });
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plans_qualification_id_qualifications_id_53407ea2');
        });
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plans_manager_user_id_users_id_c21afea8');
        });
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plans_learning_plan_type_id_learning_plan__aeb30271');
        });
        Schema::table('learning_plans', function (Blueprint $table) {
            $table->dropForeign('fk_learning_plans_student_id_students_id_a3ab6b2f');
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->dropForeign('fk_student_lesson_notes_user_id_users_id_634013eb');
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->dropForeign('fk_student_lesson_notes_note_type_id_note_types_id_e426dbfd');
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->dropForeign('fk_student_lesson_notes_lesson_session_id_lesson_sessi_f8198ef0');
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->dropForeign('fk_student_lesson_notes_student_id_students_id_2207da6d');
        });
        Schema::table('student_lesson_notes', function (Blueprint $table) {
            $table->dropForeign('fk_student_lesson_notes_student_lesson_reservation_id__5e64d2af');
        });
        Schema::table('lesson_notes', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_notes_user_id_users_id_ab0e54ee');
        });
        Schema::table('lesson_notes', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_notes_note_type_id_note_types_id_1c21de8f');
        });
        Schema::table('lesson_notes', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_notes_lesson_session_id_lesson_sessions_id_6e067c46');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign('fk_attendances_recorded_by_user_id_users_id_96d54ca7');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign('fk_attendances_lesson_session_id_lesson_sessions_id_80b4bad9');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign('fk_attendances_student_id_students_id_be738904');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign('fk_attendances_student_lesson_reservation_id_student_l_e41b1849');
        });
        Schema::table('student_lesson_reservations', function (Blueprint $table) {
            $table->dropForeign('fk_student_lesson_reservations_original_reservation_id_05e03218');
        });
        Schema::table('student_lesson_reservations', function (Blueprint $table) {
            $table->dropForeign('fk_student_lesson_reservations_lesson_session_id_lesso_d6b37f7c');
        });
        Schema::table('student_lesson_reservations', function (Blueprint $table) {
            $table->dropForeign('fk_student_lesson_reservations_student_id_students_id_1b45ba8c');
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_sessions_teacher_user_id_users_id_5a645046');
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_sessions_lesson_type_id_lesson_types_id_7e950edc');
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_sessions_course_id_courses_id_7ffe8994');
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_sessions_classroom_id_classrooms_id_2ad56d93');
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_sessions_school_id_schools_id_b3707ff3');
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_sessions_calendar_event_id_calendar_events_i_4c50ad9a');
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_sessions_lesson_schedule_id_lesson_schedules_4fc83426');
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_schedules_teacher_user_id_users_id_0fece965');
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_schedules_lesson_type_id_lesson_types_id_1fdd7b5d');
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_schedules_course_id_courses_id_db7fd7f0');
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_schedules_classroom_id_classrooms_id_dcb6424c');
        });
        Schema::table('lesson_schedules', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_schedules_school_id_schools_id_22ace451');
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign('fk_calendar_events_created_by_user_id_users_id_b107e0ec');
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign('fk_calendar_events_lesson_type_id_lesson_types_id_224cf3c5');
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign('fk_calendar_events_classroom_id_classrooms_id_7d7a4e0f');
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign('fk_calendar_events_school_id_schools_id_f4c09160');
        });
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign('fk_classrooms_school_id_schools_id_e9ce602f');
        });
        Schema::table('student_course_contracts', function (Blueprint $table) {
            $table->dropForeign('fk_student_course_contracts_course_id_courses_id_ad3af650');
        });
        Schema::table('student_course_contracts', function (Blueprint $table) {
            $table->dropForeign('fk_student_course_contracts_student_id_students_id_c4588bc3');
        });
        Schema::table('student_course_contracts', function (Blueprint $table) {
            $table->dropForeign('fk_student_course_contracts_course_price_id_course_pri_13fbc323');
        });
        Schema::table('course_prices', function (Blueprint $table) {
            $table->dropForeign('fk_course_prices_course_id_courses_id_4f70d196');
        });
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign('fk_teachers_user_id_users_id_a45a4e01');
        });
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign('fk_teachers_teacher_status_id_teacher_statuses_id_55d9f10f');
        });
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign('fk_teachers_employment_type_id_employment_types_id_01bef89c');
        });
        Schema::table('parent_students', function (Blueprint $table) {
            $table->dropForeign('fk_parent_students_student_id_students_id_d7ec12e6');
        });
        Schema::table('parent_students', function (Blueprint $table) {
            $table->dropForeign('fk_parent_students_parent_id_parents_id_d2988e4e');
        });
        Schema::table('parents', function (Blueprint $table) {
            $table->dropForeign('fk_parents_user_id_users_id_f3b70e41');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign('fk_students_user_id_users_id_bf48e82b');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign('fk_students_school_id_schools_id_66a9d4e9');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign('fk_students_enrollment_status_id_enrollment_statuses_i_590955a9');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign('fk_students_grade_id_grades_id_91b65081');
        });
        Schema::table('school_users', function (Blueprint $table) {
            $table->dropForeign('fk_school_users_role_id_roles_id_05918771');
        });
        Schema::table('school_users', function (Blueprint $table) {
            $table->dropForeign('fk_school_users_user_id_users_id_b11b50c4');
        });
        Schema::table('school_users', function (Blueprint $table) {
            $table->dropForeign('fk_school_users_school_id_schools_id_022272ee');
        });
        Schema::table('schools', function (Blueprint $table) {
            $table->dropForeign('fk_schools_area_id_areas_id_2f486c54');
        });
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropForeign('fk_role_permissions_permission_id_permissions_id_2e7b52df');
        });
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropForeign('fk_role_permissions_role_id_roles_id_ee90dfa6');
        });
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropForeign('fk_user_roles_role_id_roles_id_432a689d');
        });
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropForeign('fk_user_roles_user_id_users_id_e1b0dc50');
        });
        Schema::table('lesson_makeups', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['attendance_id']);
            $table->dropForeign(['original_lesson_session_id']);
            $table->dropForeign(['makeup_lesson_session_id']);
            $table->dropForeign(['requested_by_user_id']);
            $table->dropForeign(['approved_by_user_id']);
        });
    }
};

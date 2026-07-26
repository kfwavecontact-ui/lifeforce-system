<?php

namespace App\Http\Controllers\Admin\Education;

use App\Http\Controllers\Controller;
use App\Services\Routine\EducationRoutineService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 教育＞ルーティン割当・割当済ルーティン状況のHTTP入口。
 * 一覧取得・集計・割当処理はEducationRoutineServiceへ集約し、BladeからDBへアクセスしない。
 * 関連画面: ルーティン割当／割当済ルーティン状況
 * 参照DB: students, routine_packages, routine_package_items, routine_contents,
 * student_routines, student_routine_items, student_routine_daily_statuses,
 * routine_study_sessions, routine_study_results
 * 更新DB: student_routines, student_routine_items
 */
class RoutineController extends Controller
{
    public function __construct(private readonly EducationRoutineService $service) {}

    public function index(Request $request)
    {
        return view('admin.education.routines.index', $this->service->assignmentScreen($request));
    }

    /**
     * 廃止した『現在の取組』旧URLを、実施中で絞り込んだ割当済ルーティン状況へ転送する。
     */
    public function status(Request $request)
    {
        return redirect()->route('admin.education.routines.history', ['status' => 'active']);
    }

    public function history(Request $request)
    {
        return view('admin.education.routines.history', $this->service->historyScreen($request));
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'student_ids' => ['required','array','min:1'],
            'student_ids.*' => ['integer','exists:students,id'],
            'routine_package_id' => ['required','integer','exists:routine_packages,id'],
            'name' => ['nullable','string','max:255'],
            'start_date' => ['required','date'],
            'end_date' => ['nullable','date','after_or_equal:start_date'],
            'description' => ['nullable','string'],
            'allow_duplicate' => ['nullable','boolean'],
            'items' => ['nullable','array'],
            'items.*.enabled' => ['nullable','boolean'],
            'items.*.item_name' => ['nullable','string','max:255'],
            'items.*.is_required' => ['nullable','boolean'],
            'items.*.completion_type_id' => ['nullable','integer','exists:routine_completion_types,id'],
            'items.*.order_no' => ['nullable','integer','min:0'],
            'items.*.target_value' => ['nullable','numeric','min:0'],
            'items.*.required_days' => ['nullable','integer','min:0'],
            'items.*.estimated_minutes' => ['nullable','integer','min:0'],
            'items.*.memo' => ['nullable','string'],
        ]);

        $result = $this->service->assignPackage($data, optional($request->user())->id);
        return redirect()->route('admin.education.routines.index', ['view'=>'students','student_id'=>$data['student_ids'][0]])
            ->with('status', $result['message']);
    }

    public function cancel(Request $request, int $studentRoutine)
    {
        $result = $this->service->cancelAssignment($studentRoutine, optional($request->user())->id);

        return redirect()->route('admin.education.routines.index', [
            'view' => 'students',
            'student_id' => $result['student_id'],
        ])->with('status', $result['message']);
    }

    /**
     * 生徒ルーティンの停止・再開・完了を更新する。
     */
    public function updateState(Request $request, int $studentRoutine)
    {
        $data = $request->validate([
            'action' => ['required', 'in:stop,resume,complete'],
        ]);
        $result = $this->service->updateAssignmentState($studentRoutine, $data['action'], optional($request->user())->id);

        return back()->with('status', $result['message']);
    }


    /**
     * 割当済みルーティンアイテム単位の状況詳細をJSONで返す。
     */
    public function historyItem(int $studentRoutineItem)
    {
        return response()->json($this->service->itemHistoryDetail($studentRoutineItem));
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->service->exportHistoryCsv($request);
    }
}

@extends('layouts.admin')
@section('title','商品交換所｜申請情報')
@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/wakuwaku/reward-exchange.css') }}">
<div class="rex-page"><header class="rex-header"><div><p>わくわく ＞ 商品交換所 ＞ 申請情報</p><h1>申請情報</h1><span>未完了の申請だけを表示し、承認・却下・準備・発送・受渡を管理します。完了・却下・取消は交換履歴で確認できます。</span></div><div class="rex-header-links"><a href="{{ route('admin.wakuwaku.reward-exchange.products') }}">商品一覧</a><a href="{{ route('admin.wakuwaku.reward-exchange.history') }}">交換履歴</a></div></header>
@include('admin.wakuwaku.reward-exchange.flash')
<section class="rex-summary">@foreach(['requested'=>'申請中','approved'=>'承認済','preparing'=>'準備中','shipped'=>'発送済'] as $k=>$l)<article class="summary-{{ $k }}"><strong>{{ number_format($summary[$k]) }}</strong><span>{{ $l }}</span></article>@endforeach</section>
@include('admin.wakuwaku.reward-exchange.request-filter')
<section class="rex-table-card"><table class="rex-application-table"><thead><tr><th>申請番号／日時</th><th>生徒／教室</th><th>商品</th><th>数量／ポイント</th><th>受渡</th><th>状態</th><th>処理状態</th><th>担当／更新</th><th>操作</th></tr></thead><tbody>
@forelse($rows as $row)
@php
$image=$row->item?->mainImage?asset('storage/'.$row->item->mainImage->image_path):null;
$inventoryLabels=['reserved'=>'在庫予約済','released'=>'在庫予約解除','deducted'=>'在庫確定','none'=>'未処理','unmanaged'=>'在庫管理なし'];
$pointLabels=['deducted'=>'ポイント減算済','returned'=>'ポイント返還済','refunded'=>'ポイント返還済','none'=>'未処理'];
$actions=[]; foreach($statuses as $value=>$label){if($row->statusEnum()->canTransitionTo(\App\Enums\RewardExchangeStatus::from($value)))$actions[$value]=$label;}
$actionData=json_encode(['id'=>$row->id,'requestNumber'=>$row->request_number,'student'=>trim(($row->student?->last_name??'').' '.($row->student?->first_name??'')),'item'=>$row->item?->name??'削除済み商品','quantity'=>(int)$row->quantity,'points'=>(int)$row->request_points,'currentStatus'=>$row->status_name,'deliveryMethod'=>$row->delivery_method,'trackingNumber'=>$row->tracking_number,'note'=>$row->note,'actions'=>$actions,'url'=>route('admin.wakuwaku.reward-exchange.transition',$row)],JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT);
@endphp
<tr><td><b>{{ $row->request_number }}</b><small>{{ optional($row->requested_at)->format('Y/m/d H:i') }}</small></td><td><b>{{ $row->student?->last_name }} {{ $row->student?->first_name }}</b><small>{{ $row->student?->school?->name ?? '教室未設定' }}</small></td><td><div class="rex-table-product">@if($image)<img src="{{ $image }}" alt="">@else<span>🎁</span>@endif<div><b>{{ $row->item?->name ?? '削除済み商品' }}</b><small>{{ $row->item?->code }}</small></div></div></td><td>{{ $row->quantity }}個<small>{{ number_format($row->request_points) }} pt</small></td><td><span class="rex-delivery-icon">{{ $row->delivery_method==='shipping'?'🚚':'🏫' }}</span>{{ $row->delivery_method==='shipping'?'発送':'教室受渡' }}<small>{{ $row->tracking_number ?: '-' }}</small></td><td><span class="rex-status s-{{ $row->status }}">{{ $row->status_name }}</span><small>承認：{{ optional($row->approved_at)->format('m/d H:i') ?? '-' }}</small></td><td><div class="rex-process-badges"><span class="rex-process-pill is-inventory">{{ $inventoryLabels[$row->inventory_status]??$row->inventory_status }}</span><span class="rex-process-pill is-point">{{ $pointLabels[$row->point_status]??$row->point_status }}</span></div></td><td><span class="rex-handler {{ $row->handler ? 'is-assigned' : 'is-unassigned' }}">{{ $row->handler?->name ?? '未担当' }}</span><small>{{ $row->updated_at->format('Y/m/d H:i') }}</small></td><td><div class="rex-row-actions"><button type="button" class="rex-detail" data-url="{{ route('admin.wakuwaku.reward-exchange.show',$row) }}">詳細を見る</button><button type="button" class="rex-process-button" data-action="{{ $actionData }}" @disabled(empty($actions))>{{ empty($actions)?'処理完了':'処理する' }}</button></div></td></tr>
@empty<tr><td colspan="9" class="rex-empty"><span>📭</span><b>未処理の申請はありません</b></td></tr>@endforelse
</tbody></table></section><div class="rex-list-footer"><span>全 {{ number_format($rows->total()) }} 件中 {{ number_format($rows->firstItem() ?? 0) }}〜{{ number_format($rows->lastItem() ?? 0) }} 件を表示</span><div class="rex-pagination">{{ $rows->links() }}</div></div></div>
<div id="rex-process-modal" class="rex-modal" hidden>
    <div class="rex-modal-card rex-process-card" role="dialog" aria-modal="true" aria-labelledby="rex-process-title">
        <button type="button" class="rex-process-close rex-modal-close" aria-label="閉じる">×</button>

        <div class="rex-process-header">
            <div class="rex-process-header-icon" aria-hidden="true">✓</div>
            <div class="rex-modal-heading">
                <span>申請処理</span>
                <h2 id="rex-process-title">申請内容を処理</h2>
                <p>申請内容と処理による影響を確認してから実行してください。</p>
            </div>
        </div>

        <form method="POST" id="rex-process-form">
            @csrf
            @method('PATCH')

            <section class="rex-process-section" aria-labelledby="rex-process-summary-title">
                <div class="rex-process-section-title">
                    <span>1</span>
                    <div>
                        <h3 id="rex-process-summary-title">申請内容</h3>
                        <p>対象となる生徒・商品・ポイントを確認します。</p>
                    </div>
                </div>

                <div class="rex-process-request-number">
                    <span>申請番号</span>
                    <strong id="rex-process-number"></strong>
                    <em id="rex-process-current"></em>
                </div>

                <div class="rex-process-summary-grid">
                    <article>
                        <span class="rex-process-summary-icon" aria-hidden="true">👤</span>
                        <div><small>生徒</small><strong id="rex-process-student"></strong></div>
                    </article>
                    <article>
                        <span class="rex-process-summary-icon" aria-hidden="true">🎁</span>
                        <div><small>商品</small><strong id="rex-process-item"></strong></div>
                    </article>
                    <article class="is-wide">
                        <span class="rex-process-summary-icon" aria-hidden="true">P</span>
                        <div><small>数量／使用ポイント</small><strong id="rex-process-amount"></strong></div>
                    </article>
                </div>
            </section>

            <section class="rex-process-section" aria-labelledby="rex-process-action-title">
                <div class="rex-process-section-title">
                    <span>2</span>
                    <div>
                        <h3 id="rex-process-action-title">実行する処理</h3>
                        <p>現在の状態から実行できる処理のみ選択できます。</p>
                    </div>
                </div>

                <label class="rex-process-field rex-process-select-field">
                    <span>処理内容 <b>必須</b></span>
                    <select name="status" id="rex-process-status" required>
                        <option value="">処理を選択してください</option>
                    </select>
                </label>

                <div id="rex-process-impact" class="rex-process-impact" role="status"></div>

                <div class="rex-process-conditional-fields">
                    <label id="rex-tracking-wrap" class="rex-process-field" hidden>
                        <span>追跡番号 <b>必須</b></span>
                        <input name="tracking_number" id="rex-process-tracking" maxlength="100" placeholder="例：1234-5678-9012">
                        <small>発送伝票に記載された番号を入力してください。</small>
                    </label>

                    <label id="rex-reason-wrap" class="rex-process-field" hidden>
                        <span>理由 <b>必須</b></span>
                        <textarea name="reason" id="rex-process-reason" rows="3" maxlength="1000" placeholder="却下・取消の理由を入力してください"></textarea>
                        <small>申請者への説明として確認できる内容を入力してください。</small>
                    </label>
                </div>
            </section>

            <section class="rex-process-section rex-process-note-section" aria-labelledby="rex-process-note-title">
                <div class="rex-process-section-title">
                    <span>3</span>
                    <div>
                        <h3 id="rex-process-note-title">管理メモ</h3>
                        <p>運営内で共有したい補足がある場合に入力します。</p>
                    </div>
                </div>
                <label class="rex-process-field">
                    <span>管理メモ <i>任意</i></span>
                    <textarea name="note" id="rex-process-note" rows="3" maxlength="1000" placeholder="例：7月25日に教室で受渡予定"></textarea>
                </label>
            </section>

            <div id="rex-process-error" class="rex-inline-error" hidden></div>

            <div class="rex-process-footer">
                <p><span aria-hidden="true">ⓘ</span> 実行後はポイント・在庫・会計情報が連動して更新される場合があります。</p>
                <div class="rex-form-actions">
                    <button type="button" class="rex-process-close rex-cancel-button">キャンセル</button>
                    <button type="submit" id="rex-process-submit">この内容で実行</button>
                </div>
            </div>
        </form>
    </div>
</div>
@include('admin.wakuwaku.reward-exchange.detail-drawer')<script src="{{ asset('js/admin/wakuwaku/reward-exchange.js') }}"></script>
@endsection

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('role_permissions')->delete();
        DB::table('permissions')->delete();

        $permissions = [
            ['Core','ダッシュボード','dashboard','閲覧','view'],
            ['Core','ダッシュボード','dashboard','今日の教室閲覧','today_view'],
            ['Core','ダッシュボード','dashboard','教室分析閲覧','classroom_analysis_view'],
            ['Core','ダッシュボード','dashboard','講師分析閲覧','teacher_analysis_view'],
            ['Core','ダッシュボード','dashboard','生徒分析閲覧','student_analysis_view'],
            ['Core','ダッシュボード','dashboard','マーケティング分析閲覧','marketing_analysis_view'],
            ['Core','ダッシュボード','dashboard','営業分析閲覧','sales_analysis_view'],
            ['Core','ダッシュボード','dashboard','経営分析閲覧','management_analysis_view'],
            ['Core','ダッシュボード','dashboard','カスタマーサクセス分析閲覧','customer_success_analysis_view'],

            ['Core','教室','classroom','一覧閲覧','list_view'],
            ['Core','教室','classroom','登録','create'],
            ['Core','教室','classroom','編集','edit'],
            ['Core','教室','classroom','削除','delete'],

            ['Core','スケジュール','schedule','閲覧','view'],
            ['Core','スケジュール','schedule','編集','edit'],

            ['人','生徒','student','一覧閲覧','list_view'],
            ['人','生徒','student','登録','create'],
            ['人','生徒','student','編集','edit'],
            ['人','生徒','student','削除','delete'],

            ['人','講師','teacher','一覧閲覧','list_view'],
            ['人','講師','teacher','登録','create'],
            ['人','講師','teacher','編集','edit'],
            ['人','講師','teacher','削除','delete'],

            ['人','フォロー','follow','一覧閲覧','list_view'],
            ['人','フォロー','follow','登録','create'],
            ['人','フォロー','follow','編集','edit'],
            ['人','フォロー','follow','削除','delete'],
            ['人','フォロー','follow','履歴閲覧','history_view'],

            ['教育','ルーティン','routine','教室状況閲覧','classroom_status_view'],
            ['教育','ルーティン','routine','履歴閲覧','history_view'],
            ['教育','ルーティン','routine','ルーティン作成','create'],
            ['教育','ルーティン','routine','ルーティン編集','edit'],
            ['教育','ルーティン','routine','ルーティン削除','delete'],
            ['教育','ルーティン','routine','共通アイテム作成','common_item_create'],
            ['教育','ルーティン','routine','共通アイテム編集','common_item_edit'],
            ['教育','ルーティン','routine','共通アイテム削除','common_item_delete'],
            ['教育','ルーティン','routine','個別アイテム作成','individual_item_create'],
            ['教育','ルーティン','routine','個別アイテム編集','individual_item_edit'],
            ['教育','ルーティン','routine','個別アイテム削除','individual_item_delete'],

            ['教育','計画学習','learning_plan','教室状況閲覧','classroom_status_view'],
            ['教育','計画学習','learning_plan','履歴閲覧','history_view'],
            ['教育','計画学習','learning_plan','モデルプラン閲覧','model_plan_view'],
            ['教育','計画学習','learning_plan','作成','create'],
            ['教育','計画学習','learning_plan','編集','edit'],
            ['教育','計画学習','learning_plan','削除','delete'],

            ['教育','イベント','event','一覧閲覧','list_view'],
            ['教育','イベント','event','履歴閲覧','history_view'],
            ['教育','イベント','event','作成','create'],
            ['教育','イベント','event','編集','edit'],
            ['教育','イベント','event','削除','delete'],

            ['教育','チャレンジ','challenge','一覧閲覧','list_view'],
            ['教育','チャレンジ','challenge','予約','reserve'],
            ['教育','チャレンジ','challenge','履歴閲覧','history_view'],
            ['教育','チャレンジ','challenge','結果登録','result_create'],
            ['教育','チャレンジ','challenge','結果編集','result_edit'],

            ['わくわく','ポイント','point','残高閲覧','balance_view'],
            ['わくわく','ポイント','point','履歴閲覧','history_view'],
            ['わくわく','ポイント','point','加算','add'],
            ['わくわく','ポイント','point','減算','subtract'],

            ['わくわく','バッジ','badge','一覧閲覧','list_view'],
            ['わくわく','バッジ','badge','履歴閲覧','history_view'],
            ['わくわく','バッジ','badge','付与','grant'],
            ['わくわく','バッジ','badge','取消','revoke'],

            ['わくわく','称号','title','一覧閲覧','list_view'],
            ['わくわく','称号','title','履歴閲覧','history_view'],
            ['わくわく','称号','title','付与','grant'],
            ['わくわく','称号','title','取消','revoke'],

            ['わくわく','ランキング','ranking','閲覧','view'],

            ['わくわく','商品交換所','reward_exchange','商品閲覧','product_view'],
            ['わくわく','商品交換所','reward_exchange','商品登録','product_create'],
            ['わくわく','商品交換所','reward_exchange','商品編集','product_edit'],
            ['わくわく','商品交換所','reward_exchange','商品削除','product_delete'],
            ['わくわく','商品交換所','reward_exchange','申請閲覧','application_view'],
            ['わくわく','商品交換所','reward_exchange','交換承認','approve'],
            ['わくわく','商品交換所','reward_exchange','交換取消','cancel'],
            ['わくわく','商品交換所','reward_exchange','交換履歴閲覧','history_view'],

            ['わくわく','ショップ','shop','商品閲覧','product_view'],
            ['わくわく','ショップ','shop','商品登録','product_create'],
            ['わくわく','ショップ','shop','商品編集','product_edit'],
            ['わくわく','ショップ','shop','商品削除','product_delete'],
            ['わくわく','ショップ','shop','注文閲覧','order_view'],
            ['わくわく','ショップ','shop','購入履歴閲覧','purchase_history_view'],

            ['運営','会計','accounting','売上閲覧','sales_view'],
            ['運営','会計','accounting','請求閲覧','invoice_view'],
            ['運営','会計','accounting','請求作成','invoice_create'],
            ['運営','会計','accounting','入金閲覧','payment_view'],
            ['運営','会計','accounting','入金登録','payment_create'],
            ['運営','会計','accounting','返金閲覧','refund_view'],
            ['運営','会計','accounting','返金登録','refund_create'],
            ['運営','会計','accounting','経費閲覧','expense_view'],
            ['運営','会計','accounting','経費登録','expense_create'],

            ['運営','連絡・メモ','contact_memo','お知らせ閲覧','notice_view'],
            ['運営','連絡・メモ','contact_memo','お知らせ送信','notice_send'],
            ['運営','連絡・メモ','contact_memo','メッセージ閲覧','message_view'],
            ['運営','連絡・メモ','contact_memo','メッセージ送信','message_send'],

            ['システム','コース管理','course_management','閲覧','view'],
            ['システム','コース管理','course_management','作成','create'],
            ['システム','コース管理','course_management','編集','edit'],
            ['システム','コース管理','course_management','削除','delete'],

            ['システム','チャレンジ管理','challenge_management','閲覧','view'],
            ['システム','チャレンジ管理','challenge_management','作成','create'],
            ['システム','チャレンジ管理','challenge_management','編集','edit'],
            ['システム','チャレンジ管理','challenge_management','削除','delete'],

            ['システム','バッジ管理','badge_management','閲覧','view'],
            ['システム','バッジ管理','badge_management','作成','create'],
            ['システム','バッジ管理','badge_management','編集','edit'],
            ['システム','バッジ管理','badge_management','削除','delete'],

            ['システム','称号管理','title_management','閲覧','view'],
            ['システム','称号管理','title_management','作成','create'],
            ['システム','称号管理','title_management','編集','edit'],
            ['システム','称号管理','title_management','削除','delete'],

            ['システム','権限管理','permission_management','閲覧','view'],
            ['システム','権限管理','permission_management','編集','edit'],

            ['システム','マスタ管理','master_management','閲覧','view'],
            ['システム','マスタ管理','master_management','作成','create'],
            ['システム','マスタ管理','master_management','編集','edit'],
            ['システム','マスタ管理','master_management','削除','delete'],

            ['システム','通知マスタ','notification_master','閲覧','view'],
            ['システム','通知マスタ','notification_master','作成','create'],
            ['システム','通知マスタ','notification_master','編集','edit'],
            ['システム','通知マスタ','notification_master','削除','delete'],

            ['システム','ロール別通知設定','role_notification_setting','閲覧','view'],
            ['システム','ロール別通知設定','role_notification_setting','編集','edit'],

            ['システム','通知履歴','notification_history','閲覧','view'],
        ];

        foreach ($permissions as $index => [$groupName, $module, $moduleKey, $displayName, $action]) {
            DB::table('permissions')->insert([
                'name' => $moduleKey . '.' . $action,
                'display_name' => $displayName,
                'group_name' => $groupName,
                'module' => $module,
                'action' => $action,
                'sort_order' => $index + 1,
                'description' => $groupName . ' ＞ ' . $module . ' ＞ ' . $displayName,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
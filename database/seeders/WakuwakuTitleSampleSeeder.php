<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * わくわく称号機能の画面・操作確認用データを冪等に投入するSeeder。
 *
 * 利用DB:
 * - titles / title_categories / title_series / title_tags / title_tag_relations
 * - title_requirement_types / title_requirements
 * - student_titles / title_histories / students / users
 *
 * 方針:
 * - 装備概念は利用しない。
 * - grant_method、status、event_typeはEnumの正式値だけを保存する。
 * - 既存の本番データは削除せず、サンプル理由を持つ行だけを更新する。
 */
class WakuwakuTitleSampleSeeder extends Seeder
{
    private const SAMPLE_PREFIX = '【称号サンプル】';

    public function run(): void
    {
        $this->assertTables();

        $studentIds = DB::table('students')->orderBy('id')->limit(8)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($studentIds === []) {
            throw new RuntimeException('studentsに生徒が存在しないため、称号サンプルを投入できません。');
        }

        $operatorId = DB::table('users')->orderBy('id')->value('id');

        DB::transaction(function () use ($studentIds, $operatorId): void {
            $categoryIds = $this->seedCategories();
            $seriesIds = $this->seedSeries($categoryIds);
            $tagIds = $this->seedTags();
            $requirementTypeIds = $this->seedRequirementTypes();
            $titleIds = $this->seedTitles($categoryIds, $seriesIds);
            $this->syncTitleTags($titleIds, $tagIds);
            $this->syncRequirements($titleIds, $requirementTypeIds);
            $assignments = $this->seedAssignments($studentIds, $titleIds, $operatorId ? (int) $operatorId : null);
            $this->seedHistories($assignments, $operatorId ? (int) $operatorId : null);
        });
    }

    private function assertTables(): void
    {
        $required = ['titles','title_categories','title_series','title_tags','title_tag_relations','title_requirement_types','title_requirements','students','student_titles','title_histories'];
        $missing = array_values(array_filter($required, fn (string $table) => ! Schema::hasTable($table)));
        if ($missing !== []) {
            throw new RuntimeException('先にMigrationを実行してください。不足テーブル: '.implode(', ', $missing));
        }
    }

    private function seedCategories(): array
    {
        $names = ['脳開発','将棋','資格','イベント','継続','挑戦','特別'];
        foreach ($names as $i => $name) {
            DB::table('title_categories')->updateOrInsert(['name'=>$name], ['display_order'=>$i+1,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        }
        return DB::table('title_categories')->whereIn('name',$names)->pluck('id','name')->map(fn($id)=>(int)$id)->all();
    }

    private function seedSeries(array $categories): array
    {
        $rows = [
            ['脳開発ベーシック','脳開発'],['集中・記憶','脳開発'],['論理・思考','脳開発'],['将棋チャレンジ','将棋'],['継続チャレンジ','継続'],['期間限定','特別'],
        ];
        foreach ($rows as $i => [$name,$category]) {
            DB::table('title_series')->updateOrInsert(['name'=>$name], ['title_category_id'=>$categories[$category] ?? null,'display_order'=>$i+1,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        }
        return DB::table('title_series')->whereIn('name',array_column($rows,0))->pluck('id','name')->map(fn($id)=>(int)$id)->all();
    }

    private function seedTags(): array
    {
        $names = ['脳開発','将棋','資格','イベント','継続','努力','初心者','中級者','上級者','限定','全国','特別'];
        foreach ($names as $i => $name) {
            DB::table('title_tags')->updateOrInsert(['name'=>$name], ['display_order'=>$i+1,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        }
        return DB::table('title_tags')->whereIn('name',$names)->pluck('id','name')->map(fn($id)=>(int)$id)->all();
    }

    private function seedRequirementTypes(): array
    {
        $rows = [['learning_count','学習回数','回'],['continuous_days','継続日数','日'],['challenge_clear','チャレンジ達成','回'],['event_participation','イベント参加','回'],['manual_only','手動判定',null]];
        foreach ($rows as $i => [$code,$name,$unit]) {
            DB::table('title_requirement_types')->updateOrInsert(['code'=>$code], ['name'=>$name,'unit'=>$unit,'display_order'=>$i+1,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        }
        return DB::table('title_requirement_types')->whereIn('code',array_column($rows,0))->pluck('id','code')->map(fn($id)=>(int)$id)->all();
    }

    private function seedTitles(array $categories, array $series): array
    {
        $rows = [
            ['はじめの一歩','最初の学習に挑戦した生徒へ贈られる称号です。','normal',10,1,true,'脳開発','脳開発ベーシック',1],
            ['集中チャレンジャー','集中して学習へ取り組み、最後までやり切った生徒へ贈られます。','normal',20,2,true,'脳開発','集中・記憶',1],
            ['記憶の達人','記憶力を使う課題で優れた成果を残した生徒へ贈られます。','rare',50,3,true,'脳開発','集中・記憶',3],
            ['論理マスター','論理的に考える課題を積み重ねた生徒へ贈られる称号です。','rare',60,4,true,'脳開発','論理・思考',3],
            ['継続の星','学習を継続し、日々の努力を積み重ねた生徒へ贈られます。','epic',100,5,true,'継続','継続チャレンジ',5],
            ['将棋の挑戦者','将棋の問題や対局へ積極的に挑戦した生徒へ贈られます。','epic',120,6,true,'将棋','将棋チャレンジ',5],
            ['生きる力レジェンド','考える力・挑戦する力・継続する力を高い水準で示した生徒へ贈られます。','legend',300,7,true,'挑戦',null,8],
            ['夏休み限定スター','夏休み期間の特別チャレンジを達成した生徒だけが獲得できる限定称号です。','limited',200,8,true,'特別','期間限定',10],
            ['おやすみ中の称号','無効状態の表示確認に使用するサンプル称号です。','normal',0,99,false,'特別',null,1],
        ];
        foreach ($rows as $i => [$name,$description,$rarity,$points,$order,$active,$category,$seriesName,$level]) {
            DB::table('titles')->updateOrInsert(['name'=>$name], [
                'title_category_id'=>$categories[$category] ?? null,'title_series_id'=>$seriesName ? ($series[$seriesName] ?? null) : null,
                'code'=>'LF-TTL-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT),'grant_method'=>'both','level'=>$level,'rarity'=>$rarity,
                'description'=>$description,'acquisition_message'=>$name.'を獲得しました！','allow_regrant'=>true,'notify_on_grant'=>true,
                'point_reward'=>$points,'is_limited'=>$rarity==='limited','display_order'=>$order,'is_active'=>$active,'condition_operator'=>'and','updated_at'=>now(),'created_at'=>now(),
            ]);
        }
        return DB::table('titles')->whereIn('name',array_column($rows,0))->pluck('id','name')->map(fn($id)=>(int)$id)->all();
    }

    private function syncTitleTags(array $titles, array $tags): void
    {
        $map = [
            'はじめの一歩'=>['脳開発','初心者'],'集中チャレンジャー'=>['脳開発','努力'],'記憶の達人'=>['脳開発','中級者'],
            '論理マスター'=>['脳開発','上級者'],'継続の星'=>['継続','努力'],'将棋の挑戦者'=>['将棋','努力'],
            '生きる力レジェンド'=>['全国','特別','上級者'],'夏休み限定スター'=>['イベント','限定','特別'],'おやすみ中の称号'=>['特別'],
        ];
        foreach ($map as $titleName => $tagNames) {
            DB::table('title_tag_relations')->where('title_id',$titles[$titleName])->delete();
            foreach ($tagNames as $tagName) {
                DB::table('title_tag_relations')->insert(['title_id'=>$titles[$titleName],'title_tag_id'=>$tags[$tagName],'created_at'=>now(),'updated_at'=>now()]);
            }
        }
    }

    private function syncRequirements(array $titles, array $types): void
    {
        $map = [
            'はじめの一歩'=>['learning_count',1],'集中チャレンジャー'=>['learning_count',10],'記憶の達人'=>['challenge_clear',5],
            '論理マスター'=>['challenge_clear',10],'継続の星'=>['continuous_days',30],'将棋の挑戦者'=>['challenge_clear',10],
            '生きる力レジェンド'=>['manual_only',1],'夏休み限定スター'=>['event_participation',1],'おやすみ中の称号'=>['manual_only',1],
        ];
        foreach ($map as $titleName => [$code,$value]) {
            DB::table('title_requirements')->where('title_id',$titles[$titleName])->delete();
            DB::table('title_requirements')->insert(['title_id'=>$titles[$titleName],'title_requirement_type_id'=>$types[$code],'requirement_type'=>$code,'requirement_value'=>$value,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    private function seedAssignments(array $students, array $titles, ?int $operatorId): array
    {
        $patterns = [
            [0,'はじめの一歩','active',32],[0,'集中チャレンジャー','active',18],[1,'はじめの一歩','active',25],[1,'記憶の達人','active',10],
            [2,'論理マスター','active',8],[3,'継続の星','active',6],[4,'将棋の挑戦者','removed',20],[5,'生きる力レジェンド','active',3],
            [6,'夏休み限定スター','active',1],[7,'記憶の達人','removed',14],
        ];
        $result=[];
        foreach ($patterns as $index => [$studentOffset,$titleName,$status,$daysAgo]) {
            $studentId=$students[$studentOffset % count($students)]; $titleId=$titles[$titleName];
            $acquiredAt=now()->subDays($daysAgo)->setTime(15,30); $removedAt=$status==='removed' ? $acquiredAt->copy()->addDays(5) : null;
            $existing=DB::table('student_titles')->where('student_id',$studentId)->where('title_id',$titleId)->where('grant_reason','like',self::SAMPLE_PREFIX.'%')->first();
            $values=['status'=>$status,'grant_method'=>'manual','acquired_at'=>$acquiredAt,'is_equipped'=>false,'granted_by'=>$operatorId,'grant_reason'=>self::SAMPLE_PREFIX.' 画面確認用の付与データです。','removed_at'=>$removedAt,'removed_by'=>$removedAt?$operatorId:null,'removal_reason_code'=>$removedAt?'requirement_not_met':null,'removal_reason_detail'=>$removedAt?self::SAMPLE_PREFIX.' 条件見直しによる取り外しです。':null,'grant_notification_sent'=>false,'removal_notification_sent'=>false,'is_displayed'=>true,'updated_at'=>now()];
            if ($existing) { DB::table('student_titles')->where('id',$existing->id)->update($values); $id=(int)$existing->id; }
            else { $id=(int)DB::table('student_titles')->insertGetId(array_merge($values,['student_id'=>$studentId,'title_id'=>$titleId,'created_at'=>now()])); }
            $result[]=['id'=>$id,'student_id'=>$studentId,'title_id'=>$titleId,'status'=>$status,'acquired_at'=>$acquiredAt,'removed_at'=>$removedAt];
        }
        return $result;
    }

    private function seedHistories(array $assignments, ?int $operatorId): void
    {
        DB::table('title_histories')->where('reason_detail','like',self::SAMPLE_PREFIX.'%')->delete();
        DB::table('title_histories')->where('reason','like',self::SAMPLE_PREFIX.'%')->delete();
        foreach ($assignments as $assignment) {
            $this->insertHistory($assignment,'granted',$assignment['acquired_at'],self::SAMPLE_PREFIX.' 学習達成による称号付与です。',$operatorId);
            if ($assignment['status']==='removed') {
                $this->insertHistory($assignment,'removed',$assignment['removed_at'],self::SAMPLE_PREFIX.' 条件見直しによる取り外しです。',$operatorId,'requirement_not_met');
            }
        }
        $source=$assignments[3];
        $this->insertHistory($source,'regranted',$source['acquired_at']->copy()->addDays(4),self::SAMPLE_PREFIX.' 条件再達成による再付与です。',$operatorId);
    }

    private function insertHistory(array $assignment,string $type,mixed $at,string $detail,?int $operatorId,?string $reasonCode=null): void
    {
        DB::table('title_histories')->insert([
            'student_title_id'=>$assignment['id'],'student_id'=>$assignment['student_id'],'title_id'=>$assignment['title_id'],'event_type'=>$type,
            'event_at'=>$at,'operated_by'=>$operatorId,'reason_code'=>$reasonCode,'reason_detail'=>$detail,'operator_id'=>$operatorId,'reason'=>$detail,
            'is_equipped'=>false,'metadata'=>json_encode(['sample_data'=>true],JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}

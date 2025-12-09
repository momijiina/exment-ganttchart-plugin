<?php

namespace App\Plugins\CustomGanttChart;

use Exceedone\Exment\Services\Plugin\PluginViewBase;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Model\CustomColumn;

class Plugin extends PluginViewBase
{
    /**
     * タスク更新API
     */
    public function update()
    {
        try {
            // リクエストからデータを取得
            $id = request()->get('id');
            $table_name = request()->get('table_name');
            $value = request()->get('value');
            
            if (empty($value) || empty($table_name) || empty($id)) {
                return response()->json(['error' => 'Missing required parameters'], 400);
            }
            
            $custom_table = \Exceedone\Exment\Model\CustomTable::getEloquent($table_name);
            if (!$custom_table) {
                return response()->json(['error' => 'Table not found: ' . $table_name], 404);
            }
            
            $custom_value = $custom_table->getValueModel($id);
            if (!$custom_value) {
                return response()->json(['error' => 'Value not found: ' . $id], 404);
            }
            
            // 値を設定して保存
            $custom_value->setValue($value)->save();
        
            
            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $custom_value
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ガントチャートビューのメイン表示
     *
     * @return \Illuminate\View\View
     */
    public function grid()
    {
        $data = $this->getGanttData();
        
        // HTMLファイルを読み込み
        $htmlPath = __DIR__ . '/resources/assets/gantt.html';
        $htmlContent = '';
        
        if (file_exists($htmlPath)) {
            $htmlContent = file_get_contents($htmlPath);
            
            // ガントデータをJSON化（エラーハンドリング付き）
            $ganttDataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            
            if ($ganttDataJson === false) {
                \Log::error('JSON encode failed:', ['error' => json_last_error_msg()]);
                $ganttDataJson = json_encode(['tasks' => [], 'error' => 'データのエンコードに失敗しました']);
            }
            
            // HTMLエスケープ（二重引用符もエスケープ）
            $escapedJson = htmlspecialchars($ganttDataJson, ENT_QUOTES, 'UTF-8');
            
            // HTMLにデータを注入
            $htmlContent = preg_replace(
                '/<div id="gantt-data-config"[^>]*><\/div>/',
                '<div id="gantt-data-config" style="display:none;" data-config="' . $escapedJson . '"></div>',
                $htmlContent
            );
        } else {
            $htmlContent = '<h1>Error: ガントチャートのファイルが見つかりません。</h1>';
        }
        
        return $this->pluginView('gantt', [
            'htmlContent' => $htmlContent,
        ]);
    }

    /**
     * ビューオプション設定フォーム
     *
     * @param $form
     * @return void
     */
    public function setViewOptionForm($form)
    {
        // 独自設定を追加
        $form->embeds('custom_options', '詳細設定', function($form) {
            $form->select('title_column', 'タイトル列')
                ->options($this->custom_table->custom_columns->pluck('column_view_name', 'id'))
                ->help('タスクのタイトルとして表示する列を選択してください。選択しない場合はデフォルトのラベルが使用されます。');
                
            $form->select('start_date_column', '開始日列')
                ->options($this->custom_table->getFilteredTypeColumns([ColumnType::DATE, ColumnType::DATETIME])->pluck('column_view_name', 'id'))
                ->required()
                ->help('タスクの開始日となる列を選択してください。カスタム列種類「日付」「日時」が候補に表示されます。');
                
            $form->select('end_date_column', '終了日列')
                ->options($this->custom_table->getFilteredTypeColumns([ColumnType::DATE, ColumnType::DATETIME])->pluck('column_view_name', 'id'))
                ->help('タスクの終了日となる列を選択してください。選択しない場合は開始日と同じ日付が使用されます。カスタム列種類「日付」「日時」が候補に表示されます。');
                
            $form->select('progress_column', '進捗率列')
                ->options($this->custom_table->getFilteredTypeColumns([ColumnType::INTEGER, ColumnType::DECIMAL, ColumnType::SELECT_VALTEXT])->pluck('column_view_name', 'id'))
                ->help('タスクの進捗率を表す列を選択してください。カスタム列種類「整数」「小数」「選択肢(値・見出し)」が候補に表示されます。');
                
            $form->select('color_column', '色指定列')
                ->options($this->custom_table->custom_columns->pluck('column_view_name', 'id'))
                ->help('タスクの色を指定する列を選択してください。列の値が「赤」「青」「緑」「黄」「紫」の場合、対応する色でタスクが表示されます。それ以外の値や値がない場合は青色で表示されます。');
                
            $form->radio('highlight_weekends', '土日をハイライト')
                ->options([
                    '1' => 'する',
                    '0' => 'しない',
                ])
                ->default('0')
                ->help('土日の列を薄い赤色でハイライト表示します。');
                
            $form->radio('show_task_name', 'タスク名列を表示')
                ->options([
                    '1' => '表示する',
                    '0' => '非表示',
                ])
                ->default('1')
                ->help('左側のタスク名列の表示/非表示を切り替えます。');
        });
        
        // フィルタ(絞り込み)の設定
        static::setFilterFields($form, $this->custom_table);
        
        // 並べ替えの設定
        static::setSortFields($form, $this->custom_table);
    }

    /**
     * ガントチャート用のデータを取得
     *
     * @return array
     */
    protected function getGanttData()
    {
        $query = $this->custom_table->getValueQuery();
        
        // データのフィルタを実施
        $this->custom_view->filterModel($query);
        
        // データのソートを実施
        $this->custom_view->sortModel($query);
        
        // 値を取得
        $items = collect();
        $query->chunk(1000, function($values) use(&$items) {
            $items = $items->merge($values);
        });
        
        // カラム情報を取得
        $titleColumnId = $this->custom_view->getCustomOption('title_column');
        $startDateColumnId = $this->custom_view->getCustomOption('start_date_column');
        $endDateColumnId = $this->custom_view->getCustomOption('end_date_column');
        $progressColumnId = $this->custom_view->getCustomOption('progress_column');
        $colorColumnId = $this->custom_view->getCustomOption('color_column');
        
        if (!$startDateColumnId) {
            return [
                'tasks' => [],
                'error' => '開始日列は必須です。ビュー設定で列を指定してください。'
            ];
        }
        
        $titleColumn = $titleColumnId ? CustomColumn::find($titleColumnId) : null;
        $startDateColumn = CustomColumn::find($startDateColumnId);
        $endDateColumn = $endDateColumnId ? CustomColumn::find($endDateColumnId) : null;
        $progressColumn = $progressColumnId ? CustomColumn::find($progressColumnId) : null;
        $colorColumn = $colorColumnId ? CustomColumn::find($colorColumnId) : null;
        
        $tasks = [];
        
        foreach ($items as $item) {
            $startDate = $item->getValue($startDateColumn->column_name);
            $endDate = $endDateColumn ? $item->getValue($endDateColumn->column_name) : null;
            
            // 開始日がない場合はスキップ
            if (!$startDate) {
                continue;
            }
            
            // 終了日がない場合は開始日と同じにする
            if (!$endDate) {
                $endDate = $startDate;
            }
            
            $title = $titleColumn 
                ? $item->getValue($titleColumn->column_name, true) 
                : $item->getLabel();
            
            // タイトルを文字列に変換して安全にする
            $title = (string)$title;
            
            $progress = 0;
            if ($progressColumn) {
                $progressValue = $item->getValue($progressColumn->column_name);
                if (is_numeric($progressValue)) {
                    $progress = floatval($progressValue);
                    // 進捗率が100を超えている場合は100にする
                    if ($progress > 100) {
                        $progress = 100;
                    }
                }
            }
            
            $color = '#4F46E5'; // デフォルトは青
            if ($colorColumn) {
                $colorValue = $item->getValue($colorColumn->column_name, true);
                $color = $this->getColorFromValue($colorValue);
            }
            
            $tasks[] = [
                'id' => $item->id,
                'title' => $title,
                'start' => $startDate,
                'end' => $endDate,
                'progress' => $progress,
                'color' => $color,
                'url' => $item->getUrl(),
                'tableName' => $this->custom_table->table_name,
                'startColumn' => $startDateColumn->column_name,
                'endColumn' => $endDateColumn ? $endDateColumn->column_name : null,
                'progressColumn' => $progressColumn ? $progressColumn->column_name : null,
            ];
        }
        
        // 土日ハイライト設定を取得
        $highlightWeekends = $this->custom_view->getCustomOption('highlight_weekends');
        
        // タスク名列の表示設定を取得
        $showTaskName = $this->custom_view->getCustomOption('show_task_name');
        // デフォルトは表示（nullの場合も表示）
        if ($showTaskName === null) {
            $showTaskName = '1';
        }
        
        return [
            'tasks' => $tasks,
            'error' => null,
            'highlightWeekends' => (bool)$highlightWeekends,
            'showTaskName' => (bool)$showTaskName,
            'taskCount' => count($tasks)
        ];
    }

    /**
     * 色名から色コードを取得
     *
     * @param string $colorValue
     * @return string
     */
    protected function getColorFromValue($colorValue)
    {
        $colorValue = mb_strtolower($colorValue);
        
        $colorMap = [
            '赤' => '#EF4444',
            'あか' => '#EF4444',
            'red' => '#EF4444',
            '青' => '#4F46E5',
            'あお' => '#4F46E5',
            'blue' => '#4F46E5',
            '緑' => '#10B981',
            'みどり' => '#10B981',
            'green' => '#10B981',
            '黄' => '#F59E0B',
            '黄色' => '#F59E0B',
            'きいろ' => '#F59E0B',
            'yellow' => '#F59E0B',
            '紫' => '#9333EA',
            'むらさき' => '#9333EA',
            'purple' => '#9333EA',
            'オレンジ' => '#F97316',
            'orange' => '#F97316',
            'ピンク' => '#EC4899',
            'pink' => '#EC4899',
            '灰色' => '#6B7280',
            'はいいろ' => '#6B7280',
            'gray' => '#6B7280',
            'grey' => '#6B7280',
        ];
        
        // 色の指定があればその色を返す
        if (isset($colorMap[$colorValue])) {
            return $colorMap[$colorValue];
        }
        
        // カラーコード形式（#で始まる）の場合はそのまま返す
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $colorValue)) {
            return $colorValue;
        }
        
        // デフォルトは青
        return '#4F46E5';
    }
}

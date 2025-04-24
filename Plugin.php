<?php

namespace App\Plugins\GanttChart;

use Exceedone\Exment\Services\Plugin\PluginViewBase;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;

class Plugin extends PluginViewBase
{
    // プラグイン独自の設定を追加する
    protected $useCustomOption = true;

    /**
     * 一覧表示時のメソッド。"grid"固定
     */
    public function grid()
    {
        $values = $this->values();
        
        // ビューを呼び出し
        return $this->pluginView('gantt', ['values' => $values]);
    }

    /**
     * このプラグイン独自のエンドポイント
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
            
            $custom_table = CustomTable::getEloquent($table_name);
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
     * ビュー設定画面で表示するオプション
     * Set view option form for setting
     *
     * @param Form $form
     * @return void
     */
    public function setViewOptionForm($form)
    {
        // 独自設定を追加する場合
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
                ->required()
                ->help('タスクの終了日となる列を選択してください。カスタム列種類「日付」「日時」が候補に表示されます。');
                
            $form->select('progress_column', '進捗率列')
                ->options($this->custom_table->getFilteredTypeColumns([ColumnType::INTEGER, ColumnType::DECIMAL])->pluck('column_view_name', 'id'))
                ->help('タスクの進捗率を表す列を選択してください。カスタム列種類「整数」「小数」が候補に表示されます。');
                
            $form->select('color_column', '色指定列')
                ->options($this->custom_table->custom_columns->pluck('column_view_name', 'id'))
                ->help('タスクの色を指定する列を選択してください。列の値が「赤」「青」「緑」の場合、対応する色でタスクが表示されます。それ以外の値や値がない場合は青色で表示されます。');
        });
        
        // フィルタ(絞り込み)の設定を行う場合
        static::setFilterFields($form, $this->custom_table);
        
        // 並べ替えの設定を行う場合
        static::setSortFields($form, $this->custom_table);
    }

    /**
     * プラグインの編集画面で設定するオプション。全ビュー共通で設定する
     *
     * @param [type] $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        // 必要な場合、追加
        // $form->text('access_key', 'アクセスキー')
        //     ->help('アクセスキーを入力してください。');
    }

    // 以下、ガントチャートで必要な処理 ----------------------------------------------------
    
    protected function values()
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
        
        $tasks = $this->getTaskItems($items);
        
        return $tasks;
    }
    
    protected function getTaskItems($items)
    {
        $start_date_column = CustomColumn::getEloquent($this->custom_view->getCustomOption('start_date_column'));
        $end_date_column = CustomColumn::getEloquent($this->custom_view->getCustomOption('end_date_column'));
        $progress_column = null;
        $title_column = null;
        $color_column = null;
        
        if ($this->custom_view->getCustomOption('progress_column')) {
            $progress_column = CustomColumn::getEloquent($this->custom_view->getCustomOption('progress_column'));
        }
        
        if ($this->custom_view->getCustomOption('title_column')) {
            $title_column = CustomColumn::getEloquent($this->custom_view->getCustomOption('title_column'));
        }
        
        if ($this->custom_view->getCustomOption('color_column')) {
            $color_column = CustomColumn::getEloquent($this->custom_view->getCustomOption('color_column'));
        }
        
        $update_url = $this->plugin->getFullUrl('update');
        
        $tasks = [];
        
        foreach ($items as $item) {
            $start_date = array_get($item, 'value.' . $start_date_column->column_name);
            $end_date = array_get($item, 'value.' . $end_date_column->column_name);
            
            //終了日がなければ開始日を
            $end_date = $end_date ?: $start_date;

            if (empty($start_date) || empty($end_date)) {
                continue;
            }
            
            $progress = 0;
            if ($progress_column) {
                $progress = (int)array_get($item, 'value.' . $progress_column->column_name, 0);
            }
            
            // タイトル列が設定されている場合はその値を使用、そうでなければデフォルトのラベルを使用
            $name = $item->getLabel();
            if ($title_column) {
                $custom_title = array_get($item, 'value.' . $title_column->column_name);
                if (!empty($custom_title)) {
                    $name = $custom_title;
                }
            }
            // タスクの基本情報を設定
            $task = [
                'id' => $item->id,
                'name' => $name,
                'start' => $start_date,
                'end' => $end_date,
                'progress' => $progress,
                'table_name' => $this->custom_table->table_name,
                'update_url' => $update_url,
                'progress_column' => $progress_column ? $progress_column->column_name : '',
                'start_column' => $start_date_column->column_name,
                'end_column' => $end_date_column->column_name
            ];
            // 色の設定
            if ($color_column) {
                $color_value = array_get($item, 'value.' . $color_column->column_name);
                if (!empty($color_value)) {
                    // 色の値に基づいてCSSクラスを設定
                    $color_value = trim(strtolower($color_value));
                    if ($color_value === '赤' || $color_value === 'red') {
                        $task['custom_class'] = 'gantt-red';
                    } elseif ($color_value === '緑' || $color_value === 'green') {
                        $task['custom_class'] = 'gantt-green';
                    }elseif (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color_value)) {
                        // カラーコードが直接指定された場合（例: #FF0000）
                        $hex = ltrim($color_value, '#');
                    
                        // 3桁の16進数カラーコードを6桁に変換する (#abc → #aabbcc)
                        if (strlen($hex) === 3) {
                            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
                        }
                        
                        // RGBに変換
                        $r = hexdec(substr($hex, 0, 2));
                        $g = hexdec(substr($hex, 2, 2));
                        $b = hexdec(substr($hex, 4, 2));
                        
                        // 各値を少し暗くする（約20%暗くする）
                        $darken_factor = 0.8;
                        $r_dark = max(0, min(255, intval($r * $darken_factor)));
                        $g_dark = max(0, min(255, intval($g * $darken_factor)));
                        $b_dark = max(0, min(255, intval($b * $darken_factor)));
                        
                        // ユニークなクラス名を生成（重複を避けるため）
                        $unique_class = 'gantt-custom-' . $hex;
                        
                        // カスタムクラスを設定
                        $task['custom_class'] = $unique_class;
                        
                        // 動的CSSスタイルを挿入するための処理を追加
                        // すでに定義されているカスタム色を追跡する静的配列
                        static $defined_custom_colors = [];
                        
                        // このカラーコードがまだスタイルとして定義されていない場合のみ追加
                        if (!isset($defined_custom_colors[$hex])) {
                            $defined_custom_colors[$hex] = true;
                            
                            // head内にスタイルを追加
                            $custom_css = "
                            <style>
                            .gantt .bar-wrapper.gantt-custom-{$hex} .bar {
                                fill: #{$hex};
                            }
                            .gantt .bar-wrapper.gantt-custom-{$hex} .bar-progress {
                                fill: " . sprintf("#%02x%02x%02x", $r_dark, $g_dark, $b_dark) . ";
                            }
                            </style>";
                            
                            // スタイルをヘッダに追加するためのフックを設定
                            if (!isset($GLOBALS['custom_gantt_styles'])) {
                                $GLOBALS['custom_gantt_styles'] = [];
                            }
                            $GLOBALS['custom_gantt_styles'][] = $custom_css;
                        }                    
                    } else {
                        // 青または他の値の場合はデフォルトで青
                        $task['custom_class'] = 'gantt-blue';
                    }
                } else {
                    // 値がない場合はデフォルトで青
                    $task['custom_class'] = 'gantt-blue';
                }
            }
            
            $tasks[] = $task;
        }
        
        return $tasks;
    }
}

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
        $value = request()->get('value');
        $custom_table = CustomTable::getEloquent(request()->get('table_name'));
        $custom_value = $custom_table->getValueModel(request()->get('id'));
        
        $custom_value->setValue($value)
            ->save();
            
        return response()->json($custom_value);
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
        
        if ($this->custom_view->getCustomOption('progress_column')) {
            $progress_column = CustomColumn::getEloquent($this->custom_view->getCustomOption('progress_column'));
        }
        
        $tasks = [];
        
        foreach ($items as $item) {
            $start_date = array_get($item, 'value.' . $start_date_column->column_name);
            $end_date = array_get($item, 'value.' . $end_date_column->column_name);
            
            if (empty($start_date) || empty($end_date)) {
                continue;
            }
            
            $progress = 0;
            if ($progress_column) {
                $progress = (int)array_get($item, 'value.' . $progress_column->column_name, 0);
            }
            
            $tasks[] = [
                'id' => $item->id,
                'name' => $item->getLabel(),
                'start' => $start_date,
                'end' => $end_date,
                'progress' => $progress,
                'table_name' => $this->custom_table->table_name
            ];
        }
        
        return $tasks;
    }
}

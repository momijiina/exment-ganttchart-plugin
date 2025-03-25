/* gantt-chart.js */
$(function() {
    // CSRFトークンの設定
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // タスクデータの取得
    const tasks = window.ganttTasks || [];
    
    if (tasks.length === 0) {
        $('#gantt').html('<div class="alert alert-info">表示するタスクがありません。開始日と終了日が設定されたデータを追加してください。</div>');
        return;
    }

    // ガントチャートの初期化
    const gantt = new Gantt("#gantt", tasks, {
        header_height: 50,
        column_width: 30,
        step: 24,
        view_mode: 'Day',
        date_format: 'YYYY-MM-DD',
        popup_trigger: 'click',
        language: 'ja',
        on_click: function(task) {
            console.log('Task clicked:', task);
        },
        on_date_change: function(task, start, end) {
            console.log('Task date changed:', task, start, end);
            updateTask(task);
        },
        on_progress_change: function(task, progress) {
            console.log('Task progress changed:', task, progress);
            updateTask(task);
        }
    });
    
    // タスク更新関数
    function updateTask(task) {
        const data = {
            id: task.id,
            table_name: task.table_name,
            value: {
                start: task.start,
                end: task.end,
                progress: task.progress
            }
        };
        
        $.ajax({
            url: 'update',
            type: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                console.log('Success:', response);
                toastr.success('タスクが更新されました');
            },
            error: function(error) {
                console.error('Error:', error);
                toastr.error('タスクの更新に失敗しました');
            }
        });
    }
    
    // 表示モード変更
    $('#view-mode').on('change', function() {
        gantt.change_view_mode($(this).val());
    });
    
    // 今日ボタン
    $('#btn-today').on('click', function() {
        gantt.scroll_to_today();
    });
});

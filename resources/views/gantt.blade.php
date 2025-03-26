<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ガントチャート</title>
    <style>
        .gantt-container {
            width: 100%;
            margin: 20px 0;
            overflow-x: auto;
        }
        .gantt-toolbar {
            margin-bottom: 20px;
        }
        .gantt-toolbar button {
            margin-right: 10px;
            padding: 5px 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 3px;
            cursor: pointer;
        }
        .gantt-toolbar button:hover {
            background-color: #e9ecef;
        }
        .gantt-toolbar select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
    </style>
    <!-- Frappe Gantt CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.css">
</head>
<body>
    <div class="gantt-toolbar">
        <button id="btn-today">今日</button>
        <select id="view-mode">
            <option value="Day">日</option>
            <option value="Week">週</option>
            <option value="Month">月</option>
            <option value="Year">年</option>
        </select>
    </div>
    
    <div class="gantt-container">
        <svg id="gantt"></svg>
    </div>

    <!-- Frappe Gantt JS -->
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.umd.js"></script>
    
    <script>
        // タスクデータの取得
        const tasks = @json($values);
        
        function initGanttChart() {
            if (!document.getElementById('gantt')) {
                console.error('Gantt element not found');
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

            // 表示モード変更
            document.getElementById('view-mode').addEventListener('change', function(e) {
                gantt.change_view_mode(e.target.value);
            });
            
            // 今日ボタン
            document.getElementById('btn-today').addEventListener('click', function() {
                gantt.scroll_to_today();
            });
        }
    
        
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
            
            fetch('update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        window.addEventListener('load', function() {
            console.log('Window load event fired');
            initGanttChart();
        });

        // 即時実行関数で初期化も試みる
        (function() {
            console.log('Immediate execution');
            // DOMが既に読み込まれている場合は即時実行
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(initGanttChart, 1);
            }
        })();    
    </script>
</body>
</html>

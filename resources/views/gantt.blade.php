<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .toast {
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        .toast.success {
            background-color: #28a745;
        }
        .toast.error {
            background-color: #dc3545;
        }
        .toast.show {
            opacity: 1;
        }
        
        /* タスクの色カスタマイズ用のスタイル */
        .gantt .bar-wrapper.gantt-red .bar {
            fill: #ff5252;
        }
        .gantt .bar-wrapper.gantt-red .bar-progress {
            fill: #d32f2f;
        }
        
        .gantt .bar-wrapper.gantt-blue .bar {
            fill: #4285f4;
        }
        .gantt .bar-wrapper.gantt-blue .bar-progress {
            fill: #1a73e8;
        }
        
        .gantt .bar-wrapper.gantt-green .bar {
            fill: #4caf50;
        }
        .gantt .bar-wrapper.gantt-green .bar-progress {
            fill: #388e3c;
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

    <div class="toast-container"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Frappe Gantt JS -->
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.umd.js"></script>
    
    <script>
        // タスクデータをPHPから取得してJavaScriptで利用できるようにする
        window.ganttTasks = @json($values);
        
        // CSRFトークンの取得
        function getCSRFToken() {
            if (typeof LA !== 'undefined' && LA.token) {
                return LA.token;
            }
            
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            if (metaToken) {
                return metaToken.getAttribute('content');
            }
            
            const tokenCookie = document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='));
            if (tokenCookie) {
                return decodeURIComponent(tokenCookie.split('=')[1]);
            }
            
            console.error('No CSRF token found');
            return '';
        }
        
        function showCustomToast(message, type) {
            const toastContainer = document.querySelector('.toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            toastContainer.appendChild(toast);
            
            // 表示アニメーション
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // 数秒後に消える
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toastContainer.removeChild(toast);
                }, 300);
            }, 3000);
        }
        
        function showToast(message, type) {
            
            if (typeof toastr !== 'undefined') {
                if (type === 'success') {
                    toastr.success(message);
                } else if (type === 'error') {
                    toastr.error(message);
                } else {
                    toastr.info(message);
                }
            } else {
                // カスタム実装を使用
                showCustomToast(message, type);
            }
        }
        
        // 日付をYYYY-MM-DD形式に変換する関数
        function formatDateToYYYYMMDD(date) {
            if (typeof date === 'string') {
                // 既にYYYY-MM-DD形式の場合はそのまま返す
                if (/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                    return date;
                }
                // 文字列をDateオブジェクトに変換
                date = new Date(date);
            }
            
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            
            return `${year}-${month}-${day}`;
        }
        
        // 終了日を調整する関数
        function adjustEndDate(date) {
            if (typeof date === 'string') {
                date = new Date(date);
            }
            
            const adjustedDate = new Date(date);
            adjustedDate.setDate(adjustedDate.getDate() + 1);
            
            return adjustedDate;
        }
        
        // ガントチャートの初期化
        function initGanttChart() {
            if (!document.getElementById('gantt')) {
                console.error('Gantt element not found');
                return;
            }
            
            const tasks = window.ganttTasks;
            
            if (tasks.length === 0) {
                document.getElementById('gantt').innerHTML = '<div class="alert alert-info">表示するタスクがありません。開始日と終了日が設定されたデータを追加してください。</div>';
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
                    // シングルクリックでは何もしない（ダブルクリックと区別するため）
                },
                on_date_change: function(task, start, end) {
                    updateTask(task, start, end);
                },
                on_progress_change: function(task, progress) {
                    updateTaskProgress(task, progress);
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
            
            // タスクバーにダブルクリックイベントを追加（シンプルな実装）
            document.querySelector('#gantt').addEventListener('dblclick', function(event) {
                // クリックされた要素がタスクバーかその子要素かを確認
                let target = event.target;
                let barWrapper = null;
                
                // クリックされた要素またはその親要素がbar-wrapperクラスを持つか確認
                while (target && target !== this) {
                    if (target.classList && target.classList.contains('bar-wrapper')) {
                        barWrapper = target;
                        break;
                    }
                    target = target.parentElement;
                }
                
                // タスクバーがクリックされた場合
                if (barWrapper) {
                    const taskId = barWrapper.getAttribute('data-id');
                    if (taskId) {
                        const task = window.ganttTasks.find(t => t.id == taskId);
                        if (task) {
                            // タスクの詳細ページを開く
                            openTaskDetailPage(task);
                        }
                    }
                }
            });
            
            return gantt;
        }
        
        // タスクの詳細ページを開く関数（シンプルな実装）
        function openTaskDetailPage(task) {
            // データの詳細ページのURLを生成
            let url = '';
            
            // Exmentの管理画面のベースURLを取得
            if (typeof LA !== 'undefined' && LA.base_url) {
                url = LA.base_url + '/data/' + task.table_name + '/' + task.id + '?modal=1';
            } else {
                // ベースURLが取得できない場合は現在のパスから推測
                const currentPath = window.location.pathname;
                const adminIndex = currentPath.indexOf('/admin');
                if (adminIndex !== -1) {
                    const baseUrl = currentPath.substring(0, adminIndex + 6); // '/admin'を含める
                    url = baseUrl + '/data/' + task.table_name + '/' + task.id + '?modal=1';
                } else {
                    // 最後の手段として相対パスを使用
                    url = '/admin/data/' + task.table_name + '/' + task.id + '?modal=1';
                }
            }
            
            console.log('Opening task detail page:', url);
            
            // Exmentのモーダル表示機能を使用
            if (typeof Exment !== 'undefined' && typeof Exment.ModalEvent !== 'undefined' && typeof Exment.ModalEvent.ShowModal === 'function') {
                // ダミーのjQueryオブジェクトを作成
                const $dummy = $('<div>');
                Exment.ModalEvent.ShowModal($dummy, url);
            } else {
                // Exmentのモーダル機能が利用できない場合は新しいタブで開く
                window.open(url, '_blank');
            }
        }
    
        // タスク日付更新関数
        function updateTask(task, start, end) {
            // CSRFトークンの取得
            const token = getCSRFToken();
            
            console.log('Original end date from Frappe Gantt:', end);
            
            // 終了日を調整（Frappe Ganttは終了日を翌日として扱うため）
            const adjustedEnd = adjustEndDate(end);
            
            console.log('Adjusted end date for server:', adjustedEnd);
            
            // 日付をYYYY-MM-DD形式に変換
            const formattedStart = formatDateToYYYYMMDD(start);
            const formattedEnd = formatDateToYYYYMMDD(adjustedEnd);
            
            console.log('Formatted dates - Start:', formattedStart, 'End:', formattedEnd);
            
            // 値の更新
            let value = {};
            value[task.start_column] = formattedStart;
            value[task.end_column] = formattedEnd;
            
            // データの作成
            let data = {
                _token: token,
                id: task.id,
                table_name: task.table_name,
                value: value
            };
            
            console.log('Updating task dates:', data);
                    
            // AJAXを使用してデータを送信
            $.ajax({
                type: 'POST',
                url: task.update_url,
                data: data,
                success: function(response) {
                    console.log('Update success:', response);
                    showToast('タスクが更新されました！', 'success');
                    
                    // タスクオブジェクトを更新して表示を同期
                    task.start = formattedStart;
                    task.end = formattedEnd;
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    showToast('タスクの更新に失敗しました: ' + error, 'error');
                    
                    // エラーの詳細を表示
                    try {
                        const errorObj = JSON.parse(xhr.responseText);
                        console.error('Error details:', errorObj);
                    } catch (e) {
                        console.error('Could not parse error response');
                    }
                }
            });
        }
        
        // タスク進捗更新関数
        function updateTaskProgress(task, progress) {            
            // CSRFトークンの取得
            const token = getCSRFToken();
            
            // 値の更新
            let value = {};
            value[task.progress_column] = progress;
            
            // データの作成
            let data = {
                _token: token,
                id: task.id,
                table_name: task.table_name,
                value: value
            };
            
            console.log('Updating task progress:', data);
                    
            // AJAXを使用してデータを送信
            $.ajax({
                type: 'POST',
                url: task.update_url,
                data: data,
                success: function(response) {
                    console.log('Update success:', response);
                    showToast('進捗が更新されました！', 'success');
                    
                    // タスクオブジェクトを更新して表示を同期
                    task.progress = progress;
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    showToast('進捗の更新に失敗しました: ' + error, 'error');
                    
                    // エラーの詳細を表示
                    try {
                        const errorObj = JSON.parse(xhr.responseText);
                        console.error('Error details:', errorObj);
                    } catch (e) {
                        console.error('Could not parse error response');
                    }
                }
            });
        }
        
        // DOMが読み込まれたらガントチャートを初期化
        document.addEventListener('DOMContentLoaded', function() {
            initGanttChart();
        });
        
        // 即時実行関数で初期化も試みる（Exmentの環境によっては必要）
        (function() {
            // DOMが既に読み込まれている場合は即時実行
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(initGanttChart, 1);
            }
        })();
    </script>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ガントチャート</title>
    <style>
        #gantt-wrapper {
            width: 100%;
            overflow-y: hidden;
            overflow-x: hidden;
        }
        .gantt-container {
            width: 100%;
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
        .toast.info {
            background-color: #17a2b8;
        }
        /* フルスクリーンモード用のスタイル */
        .fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 9000;
            background: white;
            padding: 20px;
            box-sizing: border-box;
        }
        .btn-fullscreen {
            float: right;
            margin-left: 10px;
        }
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
        
        /* 進捗バーのハンドル */
        .progress-handle {
            fill: #fff;
            stroke: #666;
            stroke-width: 1;
            cursor: ew-resize;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .bar-wrapper:hover .progress-handle {
            opacity: 1;
        }
    </style>
    <!-- カラーコードによるカスタムスタイル -->
    @if(isset($GLOBALS['custom_gantt_styles']))
        @foreach($GLOBALS['custom_gantt_styles'] as $style)
            {!! $style !!}
        @endforeach
    @endif
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
        <button id="btn-toggle-edit" class="edit-mode-toggle" title="編集モード切替">
            <span class="edit-mode-icon">✏️</span>
            <span class="edit-mode-text">編集モード: OFF</span>
        </button>
        <button id="btn-fullscreen" class="btn-fullscreen" title="フルスクリーン表示">
            <span class="fullscreen-icon">🔍</span>
            <span class="fullscreen-text">フルスクリーン</span>
        </button>
    </div>
    
    <div id="gantt-wrapper">
        <svg id="gantt"></svg>
    </div>

    <div class="toast-container"></div>

    <script>
        // jquery.pjaxとlaravel-adminのエラーを防止するためのパッチ
        if (typeof $.pjax === 'undefined') {
            $.pjax = {
                defaults: {}
            };
        }
        
        if (typeof $.pjax.defaults === 'undefined') {
            $.pjax.defaults = {
                timeout: 650,
                push: true,
                replace: false,
                scrollTo: 0,
                maxCacheLength: 20
            };
        }
        
        // タスクデータをPHPから取得してJavaScriptで利用できるようにする
        window.ganttTasks = @json($values); //旧取得方法
        
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
        
        // 終了日を調整する関数（1日追加）
        function adjustEndDateAdd(date) {
            if (typeof date === 'string') {
                date = new Date(date);
            }
            
            const adjustedDate = new Date(date);
            adjustedDate.setDate(adjustedDate.getDate() + 1);
            
            return adjustedDate;
        }
        
        // 終了日を調整する関数（1日減算）
        function adjustEndDateSubtract(date) {
            if (typeof date === 'string') {
                date = new Date(date);
            }
            
            const adjustedDate = new Date(date);
            adjustedDate.setDate(adjustedDate.getDate() - 1);
            
            return adjustedDate;
        }
        
        // Frappe Ganttのプロトタイプを拡張して進捗率変更機能を無効化
        (function() {
            // Frappe Ganttが読み込まれているか確認
            if (typeof Gantt !== 'undefined') {
                // 進捗率変更に関連するメソッドをオーバーライド
                const originalBindProgressEvents = Gantt.prototype.bind_progress_events;
                Gantt.prototype.bind_progress_events = function() {
                    // 何もしない - 進捗率変更イベントを無効化
                    console.log('Progress events disabled');
                };
                
                // 進捗率変更に関連する他のメソッドも無効化
                Gantt.prototype.setup_progress_drag = function() {
                    // 何もしない
                };
                
                Gantt.prototype.handle_progress_drag = function() {
                    // 何もしない
                };
                
                Gantt.prototype.bar_progress_mousedown = function() {
                    // 何もしない
                    return false;
                };
                
                // 日付変更イベントをオーバーライド
                const originalSetupDateChange = Gantt.prototype.setup_date_change;
                Gantt.prototype.setup_date_change = function() {
                    originalSetupDateChange.call(this);
                    
                    // 日付変更時の処理をカスタマイズ
                    const originalUpdateBarPosition = this.update_bar_position;
                    this.update_bar_position = function(task) {
                        originalUpdateBarPosition.call(this, task);
                        
                        // 日付変更後に終了日を調整（Frappe Gantt内部での表示用）
                        if (task._end.getHours() === 0) {
                            console.log('Adjusting end date display for task:', task.name);
                        }
                    };
                };
            }
        })();
        
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
                readonly: true, // デフォルトは読み取りモード
                readonly_progress: true, // 進捗率の変更をFrappe Ganttのデフォルト機能では無効化
                today_button: false, // 英語のtodayボタンを無効化
                on_click: function(task) {
                    console.log('Task clicked:', task);
                    // シングルクリックでは何もしない（ダブルクリックと区別するため）
                },
                on_date_change: function(task, start, end) {
                    // 編集モードがオフの場合は値を更新しない
                    if (!isEditMode) {
                        return;
                    }
                    updateTask(task, start, end);
                },
                on_progress_change: function(task, progress) {
                    // このイベントは発火しないはずだが、念のため実装
                    console.log('Progress change event triggered (should not happen)');
                    updateTaskProgress(task, progress);
                }
            });

            // 表示モード変更
            document.getElementById('view-mode').addEventListener('change', function(e) {
                gantt.change_view_mode(e.target.value);
            });
            
            // 今日ボタン - 修正版
            document.getElementById('btn-today').addEventListener('click', function() {
                // 現在の日付を取得
                const today = new Date();
                
                // 日付を文字列に変換（YYYY-MM-DD形式）
                const todayStr = today.getFullYear() + '-' + 
                    String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(today.getDate()).padStart(2, '0');
                
                // ガントチャートを今日の日付にスクロール
                // 直接日付文字列を指定
                if (ganttInstance) {
                    // スクロール位置を今日に設定
                    const $svg = $(ganttInstance.$svg);
                    const $parent = $svg.parent();
                    
                    // 今日の日付に対応する位置を計算
                    const unit_width = ganttInstance.options.column_width;
                    const today_date = new Date(todayStr);
                    const start_date = ganttInstance.gantt_start;
                    
                    // 日付の差分を計算
                    const diff = Math.floor((today_date - start_date) / (24 * 60 * 60 * 1000));
                    
                    // スクロール位置を設定
                    const scroll_pos = diff * unit_width;
                    $parent.scrollLeft(scroll_pos);
                    
                    // 通知で今日の日付にスクロールしたことを表示
                    showToast('今日の日付(' + todayStr + ')にスクロールしました', 'info');
                    
                    // デバッグ情報をコンソールに出力
                    console.log('Scrolling to today:', todayStr, 'position:', scroll_pos);
                } else {
                    console.error('ガントチャートのインスタンスが見つかりません');
                }
            });
            
            // フルスクリーンボタン
            let isFullscreen = false;
            const ganttContainer = document.querySelector('.gantt-container');
            const btnFullscreen = document.getElementById('btn-fullscreen');
            
            btnFullscreen.addEventListener('click', function() {
                if (!isFullscreen) {
                    // フルスクリーンモードに切り替え
                    if (ganttContainer.requestFullscreen) {
                        ganttContainer.requestFullscreen();
                    } else if (ganttContainer.mozRequestFullScreen) { // Firefox
                        ganttContainer.mozRequestFullScreen();
                    } else if (ganttContainer.webkitRequestFullscreen) { // Chrome, Safari, Opera
                        ganttContainer.webkitRequestFullscreen();
                    } else if (ganttContainer.msRequestFullscreen) { // IE/Edge
                        ganttContainer.msRequestFullscreen();
                    }
                    
                    // ボタンテキストとアイコンを変更
                    btnFullscreen.querySelector('.fullscreen-text').textContent = '通常表示に戻す';
                    btnFullscreen.querySelector('.fullscreen-icon').textContent = '⬇️';
                    
                    // 通知
                    showToast('フルスクリーンモードに切り替えました', 'info');
                } else {
                    // 通常モードに戻す
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.mozCancelFullScreen) { // Firefox
                        document.mozCancelFullScreen();
                    } else if (document.webkitExitFullscreen) { // Chrome, Safari, Opera
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) { // IE/Edge
                        document.msExitFullscreen();
                    }
                    
                    // ボタンテキストとアイコンを元に戻す
                    btnFullscreen.querySelector('.fullscreen-text').textContent = 'フルスクリーン';
                    btnFullscreen.querySelector('.fullscreen-icon').textContent = '🔍';
                    
                    showToast('通常表示に戻しました', 'info');
                }
                
                // フルスクリーン状態を切り替え
                isFullscreen = !isFullscreen;
            });
            
            // フルスクリーン状態変更イベントのリスナー
            document.addEventListener('fullscreenchange', handleFullscreenChange);
            document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
            document.addEventListener('mozfullscreenchange', handleFullscreenChange);
            document.addEventListener('MSFullscreenChange', handleFullscreenChange);
            
            // フルスクリーン状態変更時の処理
            function handleFullscreenChange() {
                // ブラウザのフルスクリーン状態を確認
                const fullscreenElement = document.fullscreenElement || 
                                         document.webkitFullscreenElement || 
                                         document.mozFullScreenElement || 
                                         document.msFullscreenElement;
                
                // フルスクリーン状態を更新
                isFullscreen = !!fullscreenElement;
                
                // ボタンの表示を更新
                if (isFullscreen) {
                    btnFullscreen.querySelector('.fullscreen-text').textContent = '通常表示に戻す';
                    btnFullscreen.querySelector('.fullscreen-icon').textContent = '⬇️';
                } else {
                    btnFullscreen.querySelector('.fullscreen-text').textContent = 'フルスクリーン';
                    btnFullscreen.querySelector('.fullscreen-icon').textContent = '🔍';
                }
                
                // ガントチャートを再描画（フルスクリーン切替時にレイアウトが崩れることがあるため）
                if (ganttInstance) {
                    setTimeout(() => {
                        ganttInstance.render();
                    }, 100);
                }
            }
            
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
            
            // 進捗率変更のためのカスタムイベントハンドラを追加
            document.querySelector('#gantt').addEventListener('mousedown', function(event) {
                    // 編集モードがオフの場合は何も処理しない
                if (!isEditMode) {
                    return;
                }
                // クリックされた要素が進捗ハンドルかどうかを確認
                let target = event.target;
                let isProgressHandle = false;
                let progressHandle = null;
                
                // 進捗ハンドルのクリックかどうかを確認
                if (target.classList && target.classList.contains('progress-handle')) {
                    isProgressHandle = true;
                    progressHandle = target;
                }
                
                // 進捗ハンドルがクリックされた場合のみ処理
                if (isProgressHandle && progressHandle) {
                    const barWrapper = progressHandle.closest('.bar-wrapper');
                    if (barWrapper) {
                        const taskId = barWrapper.getAttribute('data-id');
                        if (taskId) {
                            const task = window.ganttTasks.find(t => t.id == taskId);
                            if (task) {
                                // 進捗率変更のためのカスタムハンドラを設定
                                setupProgressDrag(event, task, barWrapper);
                                
                                //タスク全体のドラッグを防止
                                event.preventDefault();
                                event.stopPropagation();
                            }
                        }
                    }
                }
            }, true); // キャプチャフェーズでイベントをリッスン
            
            // 進捗ハンドルを追加
            addProgressHandles();
            
            // グローバル変数にガントチャートのインスタンスを保存
            ganttInstance = gantt;
            return gantt;
        }
        
        // 進捗ハンドルを追加する関数
        function addProgressHandles() {
            // すべてのタスクバーを取得
            const barWrappers = document.querySelectorAll('.bar-wrapper');
            
            barWrappers.forEach(barWrapper => {
                const bar = barWrapper.querySelector('.bar');
                const barProgress = barWrapper.querySelector('.bar-progress');
                
                if (!bar || !barProgress) return;
                
                // 既存のハンドルを削除（再描画時の重複を防止）
                const existingHandles = barWrapper.querySelectorAll('.progress-handle');
                existingHandles.forEach(handle => handle.remove());
                
                // 進捗バーの幅と位置を取得
                const barWidth = parseFloat(bar.getAttribute('width'));
                const progressWidth = parseFloat(barProgress.getAttribute('width'));
                const barX = parseFloat(bar.getAttribute('x'));
                const barY = parseFloat(bar.getAttribute('y'));
                const barHeight = parseFloat(bar.getAttribute('height'));
                
                // 進捗ハンドルの位置を計算
                const handleX = barX + progressWidth;
                const handleY = barY;
                
                // SVG名前空間                
                const svgns = "http://www.w3.org/2000/svg";

                // 進捗ハンドルを作成
                const handle = document.createElementNS(svgns, "circle");
                handle.setAttribute("cx", handleX);
                handle.setAttribute("cy", handleY + barHeight / 2);
                handle.setAttribute("r", 4);
                handle.setAttribute("class", "progress-handle");
                
                // ハンドルをバーラッパーに追加
                barWrapper.appendChild(handle);
                
            });
        }
        
        // 進捗率ドラッグ処理のセットアップ
        function setupProgressDrag(event, task, barWrapper) {
            // 編集モードがオフの場合はドラッグを許可しない
            if (!isEditMode) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }
            // 進捗列が設定されていない場合は何もしない
            if (!task.progress_column) {
                console.warn('Progress column not set for task:', task);
                showToast('進捗列が設定されていません。', 'error');
                return;
            }
            
            // 初期位置を記録
            const startX = event.clientX;
            const bar = barWrapper.querySelector('.bar');
            const barProgress = barWrapper.querySelector('.bar-progress');
            const progressHandle = barWrapper.querySelector('.progress-handle');
            
            if (!bar || !barProgress) return;
            
            // バーの幅を取得
            const barWidth = parseFloat(bar.getAttribute('width'));
            const initialProgress = task.progress || 0;
            
            // マウスムーブイベントハンドラ
            function onMouseMove(e) {
                // 移動距離を計算
                const dx = e.clientX - startX;
                
                // 進捗率を計算（0-100の範囲に制限）
                let newProgress = initialProgress + (dx / barWidth) * 100;
                newProgress = Math.min(100, Math.max(0, newProgress));
                newProgress = Math.round(newProgress);
                
                // 進捗バーの幅を更新
                const progressWidth = (barWidth * newProgress) / 100;
                barProgress.setAttribute('width', progressWidth);
                
                // 進捗ハンドルの位置を更新
                if (progressHandle) {
                    const barX = parseFloat(bar.getAttribute('x'));
                    progressHandle.setAttribute('cx', barX + progressWidth);
                }
                
                // 進捗率テキストを更新（存在する場合）
                const progressText = barWrapper.querySelector('.bar-progress-text');
                if (progressText) {
                    progressText.textContent = `${newProgress}%`;
                }
                
                // 現在の進捗率を一時的に保存
                barWrapper.dataset.currentProgress = newProgress;
                
                // イベントの伝播を停止して、タスク全体のドラッグを防止
                e.preventDefault();
                e.stopPropagation();
            }
            
            // マウスイベントハンドラ
            function onMouseUp(e) {
                document.removeEventListener('mousemove', onMouseMove, true);
                document.removeEventListener('mouseup', onMouseUp, true);
                
                // 最終的な進捗率を取得
                const finalProgress = parseInt(barWrapper.dataset.currentProgress || initialProgress);
                
                // 進捗率が変更された場合のみ更新
                if (finalProgress !== initialProgress) {
                    // サーバーに進捗率を更新
                    updateTaskProgress(task, finalProgress);
                    
                    // タスクオブジェクトを更新
                    task.progress = finalProgress;
                }
                
                // イベントを停止して、タスク全体のドラッグを防止
                e.preventDefault();
                e.stopPropagation();
            }
            
            // イベントリスナーを追加（キャプチャフェーズで）
            document.addEventListener('mousemove', onMouseMove, true);
            document.addEventListener('mouseup', onMouseUp, true);
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

            // 編集モードがオフの場合は更新しない
            if (!isEditMode) {
                return;
            }
            // CSRFトークンの取得
            const token = getCSRFToken();
            
            // 日付をYYYY-MM-DD形式に変換
            const startDate = formatDateToYYYYMMDD(start);
            
            // 終了日を調整（Frappe Ganttは終了日を含む形式、データベースは終了日を含まない形式）
            // 終了日を1日追加して調整
            const adjustedEnd = adjustEndDateAdd(end);
            const endDate = formatDateToYYYYMMDD(adjustedEnd);
            
            console.log('Original end date:', end);
            console.log('Adjusted end date:', adjustedEnd);
            console.log('Formatted end date:', endDate);
            
            // 値の更新
            let value = {};
            value[task.start_column] = startDate;
            value[task.end_column] = endDate;
            
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
                    showToast('日付が更新されました！', 'success');
                    
                    // タスクオブジェクトを更新して表示を同期
                    task.start = startDate;
                    
                    // 表示用の終了日はFrappe Ganttの形式（終了日を含む）に合わせる
                    // データベースには終了日+1を保存するが、表示は元の終了日を使用
                    task.end = formatDateToYYYYMMDD(end);
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    showToast('日付の更新に失敗しました: ' + error, 'error');
                    
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
            // 編集モードがオフの場合は更新しない
            if (!isEditMode) {
                return;
            }         
            // 進捗列が設定されていない場合は更新しない
            if (!task.progress_column) {
                console.warn('Progress column not set for task:', task);
                showToast('進捗列が設定されていません。', 'error');
                return;
            }
            
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
        
        // ビューモード変更時に進捗ハンドルを再描画
        document.getElementById('view-mode').addEventListener('change', function() {
            // 少し遅延を入れて、ガントチャートの再描画後にハンドルを追加
            setTimeout(addProgressHandles, 100);
        });
        
        // スロットリング関数
        function throttle(func, delay) {
            let lastCall = 0;
            return function(...args) {
                const now = new Date().getTime();
                if (now - lastCall >= delay) {
                    lastCall = now;
                    func(...args);
                }
            };
        }

        // ガントチャートのスクロールイベント（マウスホイール）に対応するハンドルの再描画
        const ganttWrapper = document.getElementById('gantt-wrapper');
        if (ganttWrapper) {
            // スロットリングを適用して200msごとに実行
            const throttledAddProgressHandles = throttle(function() {
                addProgressHandles();
            }, 200);
            
            ganttWrapper.addEventListener('wheel', throttledAddProgressHandles);
        }

        // 編集モード切替の状態を保持する変数
        let isEditMode = false;
        // ガントチャートのインスタンスを保持する変数
        let ganttInstance = null;
        
        // 編集モード切替ボタンのイベントリスナー
        document.getElementById('btn-toggle-edit').addEventListener('click', function() {
            // 編集モードを切り替え
            isEditMode = !isEditMode;
            
            // ボタンの表示を更新
            const button = this;
            const textElement = button.querySelector('.edit-mode-text');
            
            if (isEditMode) {
                button.classList.add('active');
                textElement.textContent = '編集モード: ON';
            } else {
                button.classList.remove('active');
                textElement.textContent = '編集モード: OFF';
            }
            
            // ガントチャートのインスタンスが存在する場合
            if (ganttInstance) {
                // readonlyオプションを更新
                ganttInstance.options.readonly = !isEditMode;
                
                // ガントチャートを再描画
                ganttInstance.refresh(window.ganttTasks);
                
                // 編集モードに応じてメッセージを表示
                if (isEditMode) {
                    showToast('編集モードに切り替えました。タスクをドラッグして日程を変更できます。', 'info');
                } else {
                    showToast('閲覧モードに切り替えました。タスクの編集はできません。', 'info');
                }
                
                // 進捗ハンドルを再描画
                setTimeout(addProgressHandles, 100);
            }
        });
    </script>
</body>
</html>

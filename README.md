# Exment ガントチャートプラグイン
Frappe Ganttを廃止しシンプルなガントチャートへ変更します。

公式プラグインではありません。

色は赤、青、緑に対応(選択列等で設定)
※必要な色があればcssに追加してPlugin.phpファイルにも色部分も追加してください。

| 列の名前 | 列種類 |
| --- | --- |
| タイトル | 1行テキスト等 |
| 開始日 | 日付(日付と時刻含む) |
| 終了日 | 日付(日付と時刻含む) | 
| 進捗度 | 整数(0~100) |
| 色 | 赤、青、緑(初期値　追加はCSSから) またはカラーコードを使用|

意図しないドラッグを防ぐため編集モードは更新時等はオフなっています。切り替えて使用してください。

![ss1](https://github.com/user-attachments/assets/9234e509-3fd6-42fd-a4a2-93023174e1b9)

ダブルクリックで詳細を表示

![ss2](https://github.com/user-attachments/assets/09bdd8cf-05f8-4a83-a816-7a2c2b2ba869)

# カラーコードの適用方法について
カラーコードに対応しているため。直接カラーコードをいれてもらうか

選択肢 (値・見出しを登録)を使用してください。

例
![ss4](https://github.com/user-attachments/assets/7483be7d-7cba-4cbd-9580-1f00d5552284)
# 土日の追加ハイライト方法
gantt.blade.phpのstyleでいいので以下を追加(カラーコードは自由に変更してください)
```style:add.css
        .holiday-highlight {
            fill: #dcdcdc!important; /* 初期だと薄い為 */
        }
```

# このプラグインには以下が含まれます。
- **[Exment](https://github.com/exceedone/exment)**

  Exment用プラグインとして作成しています。

- **[Frappe Gantt](https://github.com/frappe/gantt)**

  ~~Frappe GanttのCDNを使用しています。~~
  
  ローカル環境を考慮してv2.1.0からCDNを削除し直接参照されるように変更しました。


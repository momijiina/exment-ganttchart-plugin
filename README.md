# Exment ガントチャートプラグイン
Exment用のガントチャートビューです。

公式プラグインではありません。

色は赤、青、緑に対応(選択列等で設定)
※必要な色があればcssに追加してPlugin.phpファイルにも色部分も追加してください。

| 列の名前 | 列種類 |
| --- | --- |
| タイトル | 1行テキスト等 |
| 開始日 | 日付(日付と時刻含む) |
| 終了日 | 日付(日付と時刻含む) | 
| 進捗度 | 整数(0~100) |
| 色 | 赤、青、緑を選択可能なものとだと楽です |

意図しないドラッグを防ぐため編集モードは更新時等はオフなっています。切り替えて使用してください。

![ss1](https://github.com/user-attachments/assets/9234e509-3fd6-42fd-a4a2-93023174e1b9)

ダブルクリックで詳細を表示

![ss2](https://github.com/user-attachments/assets/09bdd8cf-05f8-4a83-a816-7a2c2b2ba869)

# このプラグインには以下が含まれます。
- **[Exment](https://github.com/exceedone/exment)**

  Exment用プラグインとして作成しています。

- **[Frappe Gantt](https://github.com/frappe/gantt)**

  ~~Frappe GanttのCDNを使用しています。~~
  
  ローカル環境を考慮してv2.1.0からCDNを削除し直接参照されるように変更しました。


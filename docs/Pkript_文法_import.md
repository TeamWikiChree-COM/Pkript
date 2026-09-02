# Pkript/文法/import

外部スクリプトの関数を取り込むモジュール構文。

## リファレンス

### 構文

```text
import "スクリプト名";
```

- スクリプトのトップレベルにのみ記述できる
- 読み込み先で定義された関数とグローバル変数が自スクリプトのトップレベルスコープに展開される
- 同名シンボルの衝突時はエラー。上書きはしない
- 循環インポートおよび重複インポートは自動的に1回に抑制される
- 読み込まれたスクリプトの plugin_◯◯_convert などは呼ばれない

### 制限とセキュリティ

| 項目 | 定数 | 既定値 |
| --- | --- | --- |
| 最大読み込み数 | PKRIPT_MAX_IMPORTS | 16 |
| 最大深度 | PKRIPT_MAX_IMPORT_DEPTH | 4 |

自分より低い信頼度のスクリプトは原則 import できない。実行全体の信頼度は関与した全スクリプトの最低値になる（[Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) 参照）。

## 使用法

### 共通ユーティリティを読み込んで利用する

```js
import "string_utils";

function plugin_main_convert(e) {
    return "<p>" + htmlsc(truncateText(e.args[0], 50)) + "</p>";
}
```

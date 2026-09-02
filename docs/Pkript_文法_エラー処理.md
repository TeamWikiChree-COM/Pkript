# Pkript/文法/エラー処理

try / catch による例外捕捉とリソース上限エラーの扱い。

## リファレンス

### 構文

```text
try {
    // 処理
} catch (err) {
    // err.message にエラー内容と発生位置が格納される
}
```

- catch ブロックの変数名は省略できる（catch { ... }）
- return / break / continue は try を素通りする
- throw および finally はサポートしていない

### catch できないエラー

リソース制限違反によるエラーは catch を素通りし、スクリプト全体の実行を停止する。

| 上限 | 定数 |
| --- | --- |
| 実行時間 | PKRIPT_MAX_TIME |
| ステップ数 | PKRIPT_MAX_STEPS |
| メモリ使用量 | PKRIPT_MAX_MEMORY |
| 再帰深度とループ回数 | PKRIPT_MAX_DEPTH / PKRIPT_MAX_LOOP |
| 文字列長と配列要素数 | PKRIPT_MAX_STRING / PKRIPT_MAX_ARRAY |
| ページ参照と書き込み回数 | PKRIPT_MAX_READS / PKRIPT_MAX_WRITES |
| wiki.convert の呼び出し回数 | PKRIPT_MAX_CONVERT |
| 正規表現バックトラック量 | PKRIPT_REGEX_BACKTRACK |

## 使用法

### JSONのパースエラーを安全に処理する

```js
let config = {};
try {
    config = JSON.parse(wiki.source("ConfigPage"));
} catch (e) {
    console.warn("設定のパースに失敗しました:", e.message);
}
```

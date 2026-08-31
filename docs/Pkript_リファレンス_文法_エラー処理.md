# Pkript/リファレンス/文法/エラー処理

try / catch による例外捕捉とリソース上限エラーの扱い。

## リファレンス

### try / catch 構文

```text
try {
    // 処理
} catch (err) {
    // err.message にエラー内容が格納される
}
```

- catch ブロックの変数名は省略可能: catch { ... }
- throw および finally はサポートされていない

### catch できないエラー

リソース制限違反によるエラーは catch を素通りし、スクリプト全体の実行を停止する。

- 実行時間制限（PKRIPT_MAX_TIME）
- ステップ数制限（PKRIPT_MAX_STEPS）
- メモリ使用量制限（PKRIPT_MAX_MEMORY）
- 再帰・ループ回数上限
- 正規表現バックトラック上限

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

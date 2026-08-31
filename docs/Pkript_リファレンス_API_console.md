# Pkript/リファレンス/API/console

デバッグ出力のAPI。ページへの出力手段ではない。

## リファレンス

### console.log(...args) / warn(...args) / error(...args)

```text
console.log(...args)
console.warn(...args)
console.error(...args)
```

- ...args: 出力する値（複数可、空白区切りで連結）

| メソッド | 出力CSSクラス | 実行への影響 |
| --- | --- | --- |
| log | pkript-log | なし |
| warn | pkript-log-warn | なし |
| error | pkript-error | なし（実行は継続する） |

戻り値: なし

- 呼び出し時点では何も出力されない。スクリプト終了後、PKRIPT_DEBUG が有効な場合のみ戻り値HTMLの後ろにまとめて出力される
- オブジェクト・配列は深さ3まで展開される（それ以上は {...} / [...]、循環は [circular]）
- 出力はHTMLエスケープされる
- 実行途中でエラーになった場合も、その時点までのログが出力される
- PKRIPT_MAX_LOG 行 / PKRIPT_MAX_LOG_BYTES バイトを超えると記録を停止する（スクリプトは継続する）
- echo に相当する即時出力はない

## 使用法

### 変数の値を確認する

```text
console.log("args =", e.args, "opts =", e.opts);
```

### 処理の途中経過を追う

```js
const items = wiki.pages("Blog/");
console.log("page count:", items.length);
const filtered = items.filter((p) => wiki.time(p) > 0);
console.log("filtered:", filtered.length);
```

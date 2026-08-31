#author("2026-08-31T21:40:00+09:00","","")

# Pkript/リファレンス/API/Object

オブジェクト操作のユーティリティ。

## リファレンス

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| Object.keys(obj) | Array&lt;String&gt; | キー一覧の配列 |
| Object.values(obj) | Array&lt;any&gt; | 値一覧の配列 |
| Object.has(obj, key) | Boolean | キーが存在するか |

## 使用法

### オブジェクトの全エントリを処理する

```js
const obj = { a: 1, b: 2, c: 3 };
const rows = Object.keys(obj).map((k) => "<tr><td>" + htmlsc(k) + "</td><td>" + obj[k] + "</td></tr>").join("");
return "<table>" + rows + "</table>";
```

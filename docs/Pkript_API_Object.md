# Pkript/API/Object

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| Object.keys(obj) | Array&lt;String&gt; | キー一覧の配列 |
| Object.values(obj) | Array&lt;any&gt; | 値一覧の配列 |
| Object.entries(obj) | Array&lt;Array&gt; | [キー, 値] の配列 |
| Object.has(obj, key) | Boolean | キーが存在するか |
| Object.assign(target, ...sources) | Object | target に上書きコピー（破壊的） |
| Object.fromEntries(pairs) | Object | [キー, 値] の配列からオブジェクトを作る |

- Object.assign は第1引数そのものを書き換えて返す。呼び出し側の変数にも反映される
- Object.fromEntries は Object.entries の逆変換

## リファレンス

## 使用法

### オブジェクトの全エントリを処理する

```js
const obj = { a: 1, b: 2, c: 3 };
const rows = Object.keys(obj).map((k) => "<tr><td>" + htmlsc(k) + "</td><td>" + obj[k] + "</td></tr>").join("");
return "<table>" + rows + "</table>";
```

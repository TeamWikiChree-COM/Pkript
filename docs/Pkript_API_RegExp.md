# Pkript/API/RegExp

正規表現の構文、フラグ、制限は [Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md) を参照。

## リファレンス

### 生成

```text
/pattern/flags
```

変数に代入できる。

### メソッド

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| re.test(str) | Boolean | 一致するか |
| re.exec(str) | Array / null | 最初の一致の配列 [全体, グループ1, ...]。一致しない場合は null |
| re.source() | String | パターン文字列（スラッシュとフラグを除いた部分） |
| re.flags() | String | フラグ文字列 |
| re.global() | Boolean | g フラグが付いているか |

str.match / str.matchAll / str.search / str.replace / str.replaceAll / str.split も RegExp を受け取る（[Pkript/API/String](Pkript_API_String.md) 参照）。

## 使用法

### パターンに一致するか確認する

```js
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;
if (!ISO_DATE.test(e.args[0])) return "<p>日付の形式が正しくありません</p>";
```

### グループを使って文字列を変換する

```js
return text.replace(/(\d{4})-(\d{2})-(\d{2})/g, "$1年$2月$3日");
```

### 全件マッチを処理する

```js
const LINK = /\[\[(.+?)\]\]/g;
const links = [];
let m;
while ((m = LINK.exec(text)) !== null) {
    links.push(m[1]);
}
```

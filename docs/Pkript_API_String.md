# Pkript/API/String

UTF-8マルチバイト対応。長さとインデックスはすべて文字数（コードポイント数）単位。

## リファレンス

### プロパティ

- str.length: Number: 文字数（コードポイント数）

### 検索

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| str.indexOf(sub, [from]) | Number（見つからない場合 -1） | 最初の一致位置 |
| str.lastIndexOf(sub) | Number | 後方から最初の一致位置 |
| str.includes(sub) | Boolean | 部分一致するか |
| str.startsWith(sub) | Boolean | 前方一致するか |
| str.endsWith(sub) | Boolean | 後方一致するか |
| str.search(re) | Number（見つからない場合 -1） | 正規表現に最初に一致した位置 |
| str.localeCompare(other) | Number（-1 / 0 / 1） | バイト順での比較。sort のコールバックに使える |

### 変換

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| str.toUpperCase() | String | 大文字化 |
| str.toLowerCase() | String | 小文字化 |
| str.trim() | String | 前後の空白を除去 |
| str.trimStart() | String | 先頭の空白を除去 |
| str.trimEnd() | String | 末尾の空白を除去 |
| str.replace(from, to) | String | 最初の一致を置換 |
| str.replaceAll(from, to) | String | すべての一致を置換 |
| str.split(sep) | Array&lt;String&gt; | 分割して配列化 |
| str.repeat(count) | String | 指定回数繰り返した文字列 |
| str.concat(...vals) | String | 引数を文字列化して末尾に連結 |
| str.charCodeAt(index) | Number（範囲外は NaN） | 指定位置のコードポイント |
| str.codePointAt(index) | Number（範囲外は NaN） | charCodeAt と同じ |
| str.toString() / str.valueOf() | String | 文字列そのもの |

- 文字列はUTF-8で扱うため、charCodeAt もサロゲートペアではなくコードポイントを返す

### 部分文字列

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| str.substring(start, [end]) | String | start から end の直前まで（負インデックス非対応） |
| str.slice(start, [end]) | String | start から end の直前まで（負インデックス対応） |
| str.charAt(index) | String | 指定位置の1文字。範囲外は空文字列 |
| str.at(index) | String | 指定位置の1文字。-1 は最後の文字。範囲外は空文字列 |

### パディング

```text
str.padStart(len, [pad])
str.padEnd(len, [pad])
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| len | Number | 目標の文字数 |
| pad | String | 埋める文字列（省略時は半角空白） |

戻り値: String

すでに len 以上の場合はそのまま返す。PKRIPT_MAX_STRING を超える要求はメモリ確保前にエラーになる。

### 正規表現

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| str.match(re) | Array / null | 最初の一致の配列（g フラグ付きは全件） |
| str.matchAll(re) | Array | 全件一致の配列（g フラグ必須） |
| str.replace(re, to) | String | 最初の一致を置換（g フラグ付きは全件） |
| str.replaceAll(re, to) | String | 全件置換 |
| str.split(re) | Array&lt;String&gt; | 正規表現で分割 |

### spanWhile / spanUntil

```text
str.spanWhile(from, set)
str.spanUntil(from, set)
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| from | Number | 走査を開始するインデックス |
| set | String | 文字クラス（a-z のような範囲記法可。- 自体は先頭か末尾に置く） |

戻り値: Number: 停止した位置のインデックス

spanWhile: set に含まれる文字が続く間進む。
spanUntil: set に含まれない文字が続く間進む。

- 範囲として解釈されるのは昇順のときのみ（z-a は 'z', '-', 'a' の3文字の集合）
- 戻り値は停止位置なので、そのまま substring() に渡せる
- マルチバイトでも charAt / substring と同じ文字数単位で動作する

## 使用法

### 文字列を整形してHTMLに出力する

```js
return "<p>" + htmlsc(e.args[0].trim().toUpperCase()) + "</p>";
```

### 連番を0埋めする

```js
const nums = [1, 5, 12, 100];
return nums.map((n) => String(n).padStart(3, "0")).join(", ");
// "001, 005, 012, 100"
```

### 文字ごとに走査してハイライトする

```js
const IDENT = "a-zA-Z0-9_$";
let i = 0;
let out = "";
while (i < code.length) {
    const end = code.spanWhile(i, IDENT);
    if (end > i) {
        out += "<b>" + htmlsc(code.substring(i, end)) + "</b>";
        i = end;
        continue;
    }
    out += htmlsc(code.charAt(i));
    i++;
}
return "<pre>" + out + "</pre>";
```

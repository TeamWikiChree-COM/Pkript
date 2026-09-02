# Pkript/文法/変数

Pkript における変数宣言、代入、スコープの仕様。

## リファレンス

### 構文

```js
var name [= value];
let name [= value];
const name = value;
```

| キーワード | 再代入 | スコープ |
| --- | --- | --- |
| var | 可 | 宣言されたブロックのローカルスコープ |
| let | 可 | 宣言されたブロックのローカルスコープ |
| const | 不可 | 宣言されたブロックのローカルスコープ |

- 宣言子を省略した代入（x = 1）は、現在のローカルスコープへの自動宣言として扱われる
- 改行が存在する場合は行末のセミコロンを省略できる

```js
var a = 1
var b = 2
return a + b
```

### グローバル変数

関数の外側で宣言した変数はスクリプト全体のグローバル変数になる。

| 項目 | 仕様 |
| --- | --- |
| 評価順 | エントリポイント実行前に、記述順で評価される |
| 参照範囲 | 前に書いた変数は参照できる。後ろに書いた変数は参照できない |
| 関数の参照 | 初期値の式から関数を呼べる。関数は宣言順に関係なく参照可能 |
| 名前空間 | 関数と変数は同じ名前空間。同名の宣言はエラー |
| 変更の寿命 | let / var の変更はそのリクエスト内のみ有効。次のリクエストでは初期値に戻る |

## 使用法

### 3種類の宣言を使い分ける

```js
var a = 1;
let b = 2;
const c = 3;

b = 20;      // let は再代入できる
// c = 30;   // const は再代入するとエラー
```

### ブロック内で変数を再代入する

```js
let count = 0;
for (let i = 0; i < 5; i++) {
    count += i;
}
return "<p>合計: " + count + "</p>";
```

### 定数を宣言して参照する

```js
const MAX_ITEMS = 10;
const items = wiki.pages().slice(0, MAX_ITEMS);
```

### グローバル変数を関数から参照する

```js
const KEYWORDS = ["function", "return", "const"];
let count = 0;

function plugin_hello_convert(e) {
    count = count + 1;
    return KEYWORDS.includes(e.args[0]) ? "keyword" : "no";
}
```

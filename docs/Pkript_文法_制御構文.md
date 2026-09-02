# Pkript/文法/制御構文

条件分岐およびループ処理の構文仕様。

## リファレンス

### 条件分岐

```text
if (条件) { ... } else if (条件) { ... } else { ... }
```

### switch

```text
switch (値) {
    case 値1:
        break;
    default:
        break;
}
```

- 比較は厳密等価（=== 相当）で行われる。"1" と 1 は一致しない
- break を書かなければ次の case へフォールスルーする
- default はどこに置いてもよい。一致する case がない場合に実行される
- switch 内の break は switch のみを抜ける。continue は外側のループのものとして働く

### ループ

```js
while (条件) { ... }
do { ... } while (条件);
for (初期化; 条件; 更新) { ... }
for (const 要素 of 配列または文字列) { ... }
for (const キー in オブジェクト) { ... }
```

- break / continue が使える
- for..of は Array と String を走査し、要素の「値」が入る
- for..in は「キー」（文字列）が入る。Array に使うと添字が文字列になるため、配列には for..of を使う
- ループ変数は繰り返しごとに新しく束縛されるため const を使える
- do..while は本体を先に実行するため必ず1回以上実行される

### ラベル

```text
ラベル名: for (...) {
    break ラベル名;
    continue ラベル名;
}
```

- ラベルは break / continue と同一行に記述する。改行は文の終わりとして扱われる
- continue のラベルはループに付いている必要がある。ブロックへは break のみ届く
- ラベル付きの break は switch を素通りする（switch が飲み込むのはラベルなしの break のみ）
- 存在しないラベルと二重定義のラベルは解析時にエラー

## 使用法

### 配列要素を反復処理する

```js
const items = ["A", "B", "C"];
let html = "";
for (const item of items) {
    html += "<li>" + htmlsc(item) + "</li>";
}
return "<ul>" + html + "</ul>";
```

### 二重ループをラベルで抜ける

```js
outer: for (const row of rows) {
    for (const cell of row) {
        if (cell == "") continue outer;
        if (cell == "!") break outer;
    }
}
```

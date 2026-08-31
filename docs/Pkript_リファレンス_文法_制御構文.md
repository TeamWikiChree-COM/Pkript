# Pkript/リファレンス/文法/制御構文

条件分岐およびループ処理の構文仕様。

## リファレンス

### 条件分岐 (if / switch)

```text
if (条件) { ... } else if (条件) { ... } else { ... }
```

```text
switch (値) {
    case 条件1:
        break;
    default:
        break;
}
```

- switch の比較は厳密等価（=== 相当）で行われる
- break を記述しない場合は次の case へフォールスルーする

### ループ構文

```js
while (条件) { ... }
do { ... } while (条件);
for (初期化; 条件; 更新) { ... }
for (const 要素 of 配列または文字列) { ... }
for (const キー in オブジェクト) { ... }
```

- for..of は要素の「値」を走査する
- for..in は「キー」（文字列）を走査する

### ラベル

```text
ラベル名: for (...) {
    break ラベル名;
    continue ラベル名;
}
```

- ラベルは break / continue と同一行に記述する

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

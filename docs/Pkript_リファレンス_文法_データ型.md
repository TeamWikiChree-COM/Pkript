# Pkript/リファレンス/文法/データ型

Pkript で扱えるデータ型とリテラル表記の仕様。

## リファレンス

### データ型一覧

| 型名 | 説明 | リテラル例 |
| --- | --- | --- |
| String | 文字列型（UTF-8文字単位） | "text", 'text', `text` |
| Number | 数値型（浮動小数点数） | 10, 3.14, 0xff, 0b1010, 1e3, 1_000 |
| Boolean | 真偽値型 | true, false |
| Null | 空値 | null |
| Array | 配列型 | [1, "a", true] |
| Object | オブジェクト型 | { key: "value", count: 1 } |
| RegExp | 正規表現型 | /[a-z]+/i |

### 数値リテラル仕様

- 16進数: 0xff, 0XFF
- 2進数: 0b1010
- 8進数: 0o17
- 指数表記: 1e3, 2e-3
- 区切り文字: 1_000_000（数字の間にのみ記述可能）

## 使用法

### オブジェクトと配列を組み合わせて定義する

```js
const record = {
    title: "設定",
    tags: ["admin", "system"],
    enabled: true,
    version: 1
};
```

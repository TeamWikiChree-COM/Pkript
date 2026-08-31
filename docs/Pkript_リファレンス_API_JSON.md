#author("2026-08-31T21:40:00+09:00","","")

# Pkript/リファレンス/API/JSON

JSONのシリアライズ・パースのAPI。

## リファレンス

### JSON.stringify(val, [indent])

```text
JSON.stringify(val, [indent])
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| val | any | シリアライズ対象の値 |
| indent | Number | インデント幅（省略または 0 で1行出力） |

戻り値: String

- 関数はシリアライズ不可。オブジェクト内では項目ごと除外、配列内では null になる（JavaScriptと同仕様）
- 循環参照はエラー
- UTF-8文字列は \uXXXX にエスケープされず素通し
- 入れ子の深さは PKRIPT_MAX_DEPTH、サイズは PKRIPT_MAX_STRING / PKRIPT_MAX_ARRAY に従う

### JSON.parse(str)

```text
JSON.parse(str)
```

- str: String: パース対象のJSON文字列

戻り値: Object / Array / String / Number / Boolean / null

不正なJSONでエラーを投げる。try / catch で捕捉可能。

## 使用法

### Wikiページに設定をJSONで保存する

```js
let settings = {};
try {
    settings = JSON.parse(wiki.source("Settings"));
} catch (e) {}

settings.count = (settings.count || 0) + 1;
wiki.write("Settings", JSON.stringify(settings, 2));
```

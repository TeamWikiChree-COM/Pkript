#author("2026-08-31T21:40:00+09:00","","")

# Pkript/リファレンス/API/グローバル関数

グローバルスコープで直接呼び出せる関数。

## リファレンス

### htmlsc(str)

```text
htmlsc(str)
```

- str: String

戻り値: String: &amp; &lt; &gt; " ' を HTML エンティティに変換した文字列。

JSX の {} 内は自動エスケープのため、htmlsc() を重ねると二重エスケープになる。

### 型変換

| 関数 | 戻り値 | 説明 |
| --- | --- | --- |
| String(val) | String | 任意の値を文字列に変換。null は空文字列 |
| Number(val) | Number | 文字列を数値に変換 |
| Boolean(val) | Boolean | 任意の値を真偽値に変換 |

### 可変長引数

| 関数 | 戻り値 | 説明 |
| --- | --- | --- |
| func_get_args() | Array | 全引数の配列 |
| func_num_args() | Number | 引数の個数 |
| func_get_arg(n) | any | n番目の引数（0始まり） |

エントリポイントに引数名を書かずに使える。PHPプラグインとの互換性のために提供されている。

### PHPプラグイン互換エイリアス

対応するメソッドが存在する場合はそちらを使う。

| 関数 | 対応するメソッド |
| --- | --- |
| is_page(page) | wiki.exists(page) |
| make_pagelink(page, [label]) | wiki.link(page, label) |
| convert_html(text) | wiki.convert(text) |
| strip_bracket(str) | wiki.stripBracket(str) |
| encode(str) / decode(str) | wiki.encode(str) / wiki.decode(str) |
| get_source(page) | wiki.source(page) |
| get_existpages([prefix]) | wiki.pages(prefix) |
| get_filetime(page) | wiki.time(page) |
| is_freeze(page) | wiki.isFrozen(page) |
| format_date(t, [paren]) | date.format(t, paren) |

## 使用法

### 可変長引数を受け取る

```js
function plugin_hello_convert() {
    const args = func_get_args();
    return "<p>" + htmlsc(args[0] || "World") + "</p>";
}
```

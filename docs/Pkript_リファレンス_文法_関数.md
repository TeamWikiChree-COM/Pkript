# Pkript/リファレンス/文法/関数

関数の定義、アロー関数、エントリポイントの仕様。

## リファレンス

### 関数宣言

```js
function name([param1, param2, ...]) {
    // body
    return value;
}
```

- トップレベルに宣言する
- スクリプト内のどこからでも呼び出し可能（宣言順序の影響を受けない）

### アロー関数

```js
const fn = (param1, param2) => expression;
const fn = (param1, param2) => {
    // body
    return value;
};
```

- 定義元のスコープをキャプチャする（クロージャ）
- エントリポイントをアロー関数として代入・定義することも可能

### エントリポイント

| 関数名パターン | 呼び出し形式 |
| --- | --- |
| plugin_スクリプト名_convert(e) | #pkript(スクリプト名) または #スクリプト名 |
| plugin_スクリプト名_inline(e) | &amp;pkript(スクリプト名); または &amp;スクリプト名; |
| plugin_スクリプト名_action(e) | ?plugin=pkript&amp;script=スクリプト名（GET / POST） |

## 使用法

### エントリポイントを定義して出力を返す

```js
function plugin_hello_convert(e) {
    return "<p>こんにちは、" + htmlsc(e.args[0] || "ゲスト") + "さん</p>";
}
```

### 補助関数とクロージャを利用する

```js
function makeFormatter(prefix) {
    return (text) => prefix + ": " + text;
}

function plugin_log_convert(e) {
    const fmt = makeFormatter("INFO");
    return "<p>" + htmlsc(fmt(e.args[0])) + "</p>";
}
```

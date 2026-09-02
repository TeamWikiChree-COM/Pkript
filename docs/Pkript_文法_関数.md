# Pkript/文法/関数

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
- スクリプト内のどこからでも呼び出せる（宣言順序の影響を受けない）
- エントリポイント以外の関数はスクリプト内のユーティリティとして使う

### アロー関数

```js
const fn = (param1, param2) => expression;
const fn = (param1, param2) => {
    // body
    return value;
};
```

- 定義元のスコープをキャプチャする（クロージャ）
- エントリポイントをアロー関数として定義することもできる

### エントリポイント

| 関数名パターン | 呼び出し形式 | 用途 |
| --- | --- | --- |
| plugin_スクリプト名_convert(e) | #pkript(スクリプト名) または #スクリプト名 | ブロック要素（段落など） |
| plugin_スクリプト名_inline(e) | &amp;pkript(スクリプト名); または &amp;スクリプト名; | インライン要素（行内） |
| plugin_スクリプト名_action(e) | ?plugin=pkript&amp;script=スクリプト名（GET / POST） | アクション（単独ページやフォーム処理） |

#スクリプト名 形式の直接呼び出しは PKRIPT_BIND で切り替える（[Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) 参照）。

### 可変長引数

| 関数 | 戻り値 | 説明 |
| --- | --- | --- |
| func_get_args() | Array | 全引数の配列 |
| func_num_args() | Number | 引数の個数 |
| func_get_arg(n) | any | n番目の引数（0始まり） |

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

### 可変長引数を受け取る

```js
function plugin_hello_convert() {
    const args = func_get_args();
    return "<p>" + htmlsc(args[0] || "World") + "</p>";
}
```

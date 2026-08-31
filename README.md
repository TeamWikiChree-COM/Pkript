# Pkript (プクリプト)
PukiWikiのプラグインをJavaScript風の構文で書くためのスクリプト言語です。
PHPへの直接到達を遮断し、出力もサニタイズされるため、ホスティング環境や第三者によるスクリプトでも安全に動作します。

- リファレンス: https://wikichree.com/guide/?Pkript

## 配置

`plugin/` 配下のファイルを PukiWiki にコピーします。

```text
plugin/
  pkript.inc.php    # 入口と設定
  pkript/
    lib/            # ランタイム (.php)
    script/         # スクリプト (.pks / .js)
```

## クイックスタート

1. スクリプトを作成します（ファイルまたはWikiページのどちらでも動作します）。

- ファイル: `plugin/pkript/script/hello.js`
- ページ: `:config/pkript/script/hello`

```javascript
function plugin_hello_convert() {
    var args = func_get_args();
    var name = args[0] || "World";
    return "<p>Hello, " + htmlsc(name) + "!</p>";
}

function plugin_hello_inline(e) {
    var name = e.args[0] || "World";
    return "<span>Hello, " + htmlsc(name) + "!</span>";
}
```

2. ページから呼び出します。

```text
#pkript(hello, World)
&pkript(hello, World);
```

## 仕様の要点

### エントリポイント

スクリプト名に合わせた関数を定義します。

| 呼び出し | 実行される関数 | 用途 |
| --- | --- | --- |
| #pkript(name, ...) | plugin_<name>_convert(e) | ブロック |
| &pkript(name, ...); | plugin_<name>_inline(e) | インライン |
| ?plugin=pkript&script=... | plugin_<name>_action(e) | アクション |

### 引数とコンテキスト

引数は `func_get_args()` または第1引数の `e` から取れます。

```javascript
// PHPスタイル
var args = func_get_args();
var first = args[0];

// JavaScriptスタイル
var first = e.args[0];
var page = e.page;
var method = e.method;     // "GET" または "POST"
var who = e.vars["who"];    // 送信されたフォーム値 (パスワード等は自動除外)
```

### 主な言語機能

- 変数: `var`, `let`, `const`, 未宣言代入（`$args = ...` も可）。関数の外側にも書けます
- 関数: `function` 宣言、アロー関数（`const double = x => x * 2;`）
- 制御構文: `if`, `while`, `for`, `for..of`, `for..in`, `switch`, `try`/`catch`
- 配列操作: `map`, `filter`, `find`, `findIndex`, `sort`, `push`, `pop`, `join`, `slice`
- モジュール読み込み: `import "util";`
- セミコロン: 改行があれば省略可能

### HTMLの組み立て

文字列連結のほかに、テンプレートリテラルとJSX記法が使えます。

```javascript
// テンプレートリテラル
return `<p class="${cls}">${htmlsc(name)}</p>`;

// JSX記法（{} の中は自動でエスケープされます）
return <p class={cls}>{name}</p>;
```

JSXの `{}` は自動でエスケープするので、`htmlsc()` を重ねると二重エスケープになります。

### 主なAPI

- HTML / 表示: `htmlsc(str)`, `html.br(str)`, `html.strip(str)`, `wiki.link(page, [label])`, `wiki.convert(text)`
- ページ読み込み: `wiki.exists(page)`, `wiki.source(page)`, `wiki.pages([prefix])`
- ページ書き込み: `wiki.write(page, text)`, `wiki.append(page, text)`, `wiki.token()`, `wiki.canWrite(page)`
- 数学 / オブジェクト: `Math.floor`, `ceil`, `round`, `abs`, `min`, `max`, `random`, `Object.keys`, `values`, `has`

## #hello で直接呼び出す場合 (任意)

`#pkript(hello)` ではなく `#hello` で直接呼び出したい場合は、`lib/plugin.php` の `exist_plugin` にフォールバックを追加します。同名のPHPプラグインが存在する場合はそちらが優先されます。

```diff
--- a/lib/plugin.php
+++ b/lib/plugin.php
@@ -45,6 +45,11 @@ function exist_plugin($name)
 		require_once(PLUGIN_DIR . $name . '.inc.php');
 		return TRUE;
 	} else {
+		if (file_exists(PLUGIN_DIR . 'pkript.inc.php')) {
+			require_once(PLUGIN_DIR . 'pkript.inc.php');
+			if (function_exists('plugin_pkript_bind') && plugin_pkript_bind($name)) return TRUE;
+		}
 	    	$exist[$name] = FALSE;
 	    	$count[$name] = 1;
 		return FALSE;
```

## 主な設定 (plugin/pkript.inc.php)

```php
// スクリプト保存先ディレクトリ
define('PKRIPT_SCRIPT_DIR', 'plugin/pkript/script/');

// 許可する拡張子
define('PKRIPT_SCRIPT_EXT', 'pks,js');

// :config/pkript/script/<name> ページからの実行を許可するか
define('PKRIPT_ALLOW_PAGE_SCRIPT', 1);

// 凍結ページのみ実行を許可するか
define('PKRIPT_PAGE_SCRIPT_FROZEN_ONLY', 0);

// JSX記法を使えるようにするか
define('PKRIPT_JSX', 1);

// #hello のようにスクリプト名で直接呼び出せるようにするか
define('PKRIPT_BIND', 1);
```

実行時間、メモリ、ページ書き込み回数などの上限も定数で調整できます。リファレンスの `Pkript/設定` を参照してください。

## ライセンス
- MIT License

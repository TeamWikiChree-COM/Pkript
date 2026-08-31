# Pkript

Pkript（プクリプト）は、PukiWikiのプラグインをJavaScript風の構文で書くためのスクリプト言語です。
PHPへの直接アクセスを遮断し、出力もサニタイズされるため、安全にプラグインを実行できます。

- <https://github.com/TeamWikiChree-COM/Pkript>

※WikiChree.COM内では直接設定できない項目もございますが、ご了承ください。

## ドキュメント

- [Pkript/文法](Pkript_%E6%96%87%E6%B3%95.md) - 変数、関数、アロー関数、テンプレートリテラル、制御構文、try/catch、import、フォーム
  - [Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) - HTMLを式として書く記法、エスケープの規則
  - [Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md) - パターンの書き方、使えるメソッド、暴走を止める仕組み
- [Pkript/API](Pkript_API.md) - 組み込み関数、wiki / date / JSON / html / Math / Object、配列・文字列操作
- [Pkript/サンプル](Pkript_%E3%82%B5%E3%83%B3%E3%83%97%E3%83%AB.md) - 実用スクリプト集
- [Pkript/エラー一覧](Pkript_%E3%82%A8%E3%83%A9%E3%83%BC%E4%B8%80%E8%A6%A7.md) - エラーメッセージと対処法
- [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) - 設定オプション、直接呼び出し、セキュリティ

## クイックスタート

スクリプトはファイル（plugin/pkript/script/hello.js）またはWikiページ（:config/pkript/script/hello）に記述します。拡張子は .pks または .js です。

ファイル内に関数を定義します。

```js
function plugin_hello_convert(e) {
    var args = func_get_args();
    var name = args[0] || "World";
    return "<p>Hello, " + htmlsc(name) + "!</p>";
}

function plugin_hello_inline(e) {
    var name = e.args[0] || "World";
    return "<span>Hello, " + htmlsc(name) + "!</span>";
}
```

Wikiページから呼び出します。

```text
#pkript(hello, World)
&pkript(hello, World);
```

### 呼び出し形式

| 呼び出し | 実行される関数 | 用途 |
| --- | --- | --- |
| #pkript(名前, 引数) | plugin_◯◯_convert(e) | ブロック要素（段落など） |
| &amp;pkript(名前, 引数); | plugin_◯◯_inline(e) | インライン要素（行内） |
| ?plugin=pkript&amp;script=名前 | plugin_◯◯_action(e) | アクション（単独ページ・フォーム処理） |

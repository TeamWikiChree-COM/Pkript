# Pkript/サンプル

Pkriptスクリプトのサンプル集です。
plugin/pkript/script/&lt;名前&gt;.js に保存するか、:config/pkript/script/&lt;名前&gt; ページに書けば動作します。

## hello - あいさつ

引数を受け取ってHTMLを出力する基本的な例です。

plugin/pkript/script/hello.js

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

使い方:
```text
#pkript(hello, World)
&pkript(hello, World);
```

出力:
```text
<p>Hello, World!</p>
<span>Hello, World!</span>
```

## badge - 色付きラベル

style属性でインラインバッジを表示します。

plugin/pkript/script/badge.js

```js
function plugin_badge_inline(e) {
    var label = htmlsc(e.args[0] || "NEW");
    var color = e.args[1] || "#0066cc";
    var style = "display:inline-block; padding:2px 8px; border-radius:4px; "
        + "background-color:" + color + "; color:#ffffff; font-size:12px";
    return "<span style='" + style + "'>" + label + "</span>";
}
```

使い方:
```text
&badge(NEW, #cc0000);
&badge(v0.3, #0066cc);
```

## box - メッセージ枠

タイトルと本文を受け取って囲み枠を出力します。

plugin/pkript/script/box.js

```js
function plugin_box_convert(e) {
    var title = htmlsc(e.args[0] || "お知らせ");
    var text  = htmlsc(e.args[1] || "");
    return "<div style='border:1px solid #cccccc; border-radius:4px; padding:10px; margin:8px 0;'>"
        + "<div style='font-weight:bold; margin-bottom:4px;'>" + title + "</div>"
        + "<div>" + text + "</div>"
        + "</div>";
}
```

使い方:
```text
#box(お知らせ, 明日はメンテナンスを実施します。)
```

## sum - 引数の合計

渡された数値を合算して返します。

plugin/pkript/script/sum.js

```js
function plugin_sum_inline(e) {
    var total = 0;
    for (const arg of e.args) {
        total += Number(arg);
    }
    return "" + total;
}
```

使い方:
```text
&sum(10, 20, 30);
```

## pagelist - ページ一覧

wiki.pages と map メソッドを使って、指定プレフィックスのページ一覧を出力します。

plugin/pkript/script/pagelist.js

```js
function plugin_pagelist_convert(e) {
    var prefix = e.args[0] || "";
    var pages = wiki.pages(prefix);
    if (pages.length == 0) return "<p>該当するページがありません。</p>";

    var listItems = pages.map((p) => "<li>" + wiki.link(p) + "</li>").join("\n");
    return "<ul>\n" + listItems + "\n</ul>";
}
```

使い方:
```text
#pagelist(Docs/)
```

## guestbook - 投稿フォーム

フォームから入力された内容をページに追記します。

plugin/pkript/script/guestbook.js

```js
function plugin_guestbook_convert(e) {
    return "<form method=\"post\">"
        + "<input type=\"hidden\" name=\"plugin\" value=\"pkript\">"
        + "<input type=\"hidden\" name=\"script\" value=\"guestbook\">"
        + "<input type=\"hidden\" name=\"pkript_token\" value=\"" + wiki.token() + "\">"
        + "<p>お名前: <input type=\"text\" name=\"name\"></p>"
        + "<p>コメント: <textarea name=\"comment\" rows=\"3\" cols=\"40\"></textarea></p>"
        + "<p><input type=\"submit\" value=\"投稿する\"></p>"
        + "</form>";
}

function plugin_guestbook_action(e) {
    if (e.method != "POST") return "<p>POSTで送信してください。</p>";

    var name = e.vars["name"] || "名無し";
    var comment = e.vars["comment"] || "";
    if (comment == "") return "<p>コメントを入力してください。</p>";

    var entry = "\n- " + name + " : " + comment;
    wiki.append("GuestBook", entry);

    return "<p>投稿を受け付けました。<a href=\"./?GuestBook\">GuestBookへ戻る</a></p>";
}
```

使い方:
```text
#guestbook
```

### 投稿内容の扱い
wiki.append で書き込んだ内容は**Wiki記法として解釈されます**。投稿者が書いた文字列がそのままページの本文になるので、記法もプラグイン呼び出しも書けてしまいます。

PukiWiki 本体の #comment も同じ作りですが、誰でも投稿できるページで使うなら、投稿を受け取る側で記法を潰すか、書き込み先を $edit_auth_pages で保護してください。

```js
// 行頭記号と改行を潰してから書き込む例
var comment = e.vars["comment"].replaceAll("\n", " ");
if (comment.startsWith("#") || comment.startsWith("*")) comment = " " + comment;
```

## jsxcard - JSX記法のカード

JSX記法で枠付きのカードを出します。文字列連結を使わずにHTMLを組み立てます。

plugin/pkript/script/jsxcard.js

```js
// Pkript sample: #pkript(jsxcard, 見出し, class=note){{
// 本文
// }}

const COLORS = { note: "#4a6", warn: "#c62", info: "#468" };

function pickColor(kind) {
    return Object.has(COLORS, kind) ? COLORS[kind] : COLORS["info"];
}

function plugin_jsxcard_convert(e) {
    const title = e.args[0] || "(無題)";
    const kind = e.opts["class"] || "info";
    const color = pickColor(kind);
    const lines = e.body == "" ? [] : e.body.split("\n");

    return <div class={"card " + kind} style={"border-left: 4px solid " + color}>
        <p style={"color: " + color}><b>{title}</b></p>
        {lines.map((line) => <p>{line}</p>).join("")}
    </div>;
}
```

使い方:
```text
#jsxcard(お知らせ, class=warn){{
本文の1行目
本文の2行目
}}
```

出力:
```text
<div class="pkript-card pkript-warn" style="border-left:4px solid #c62">
  <p style="color:#c62"><b>お知らせ</b></p>
  <p>本文の1行目</p><p>本文の2行目</p>
</div>
```

本文に &lt;b&gt; のようなタグを書いても、{} の中の文字列は自動でエスケープされるので、タグとしては働きません。
詳しくは [Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) を参照してください。

## highlight - 文法ハイライト

Pkriptのコードに対して色付けをします。

plugin/pkript/script/highlight.js

```js
// Pkript: Syntax Highlighter
// Usage:
//   #pkript(highlight){{
//   function hello(name) {
//       console.log("Hello, " + name);
//   }
//   }}

function isAlpha(c) {
    return (c >= "a" && c <= "z") || (c >= "A" && c <= "Z") || c == "_" || c == "$";
}

function isDigit(c) {
    return c >= "0" && c <= "9";
}

function isAlphaNum(c) {
    return isAlpha(c) || isDigit(c);
}

const KEYWORDS = [
    "function", "return", "let", "const", "var", "if", "else", "while",
    "for", "of", "in", "break", "continue", "switch", "case", "default",
    "new", "typeof", "instanceof", "try", "catch", "finally", "throw",
    "class", "extends", "import", "export", "from", "as", "async", "await",
    "yield", "this", "super", "delete", "void"
];

const BUILTINS = [
    "true", "false", "null", "undefined", "NaN", "Infinity",
    "String", "Number", "Boolean", "Array", "Object", "Math", "JSON",
    "console", "document", "window", "Promise", "Symbol", "Date"
];

// Character sets for the scanner. spanWhile/spanUntil take a whole run in one
// call; a character at a time costs 50-60 steps each.
const IDENT_CHARS = "a-zA-Z0-9_$";
const NUM_CHARS = "0-9.";
const SPACE_CHARS = " \t";
// Anything that can start a token of its own
const TOKEN_START = "/\"'`0-9a-zA-Z_$";

function highlightJs(code) {
    let out = "";
    let i = 0;
    const len = code.length;

    while (i < len) {
        const c = code.charAt(i);
        const c2 = i + 1 < len ? code.charAt(i + 1) : "";

        // Single-line comment: // ...
        if (c == "/" && c2 == "/") {
            const start = i;
            i = code.spanUntil(i, "\n");
            const comment = code.substring(start, i);
            out += "<span style=\"color: #308a27; font-style: italic;\">" + htmlsc(comment) + "</span>";
            continue;
        }

        // Multi-line comment: /* ... */
        if (c == "/" && c2 == "*") {
            const start = i;
            const end = code.indexOf("*/", i + 2);
            i = end < 0 ? len : end + 2;
            const comment = code.substring(start, i);
            out += "<span style=\"color: #308a27; font-style: italic;\">" + htmlsc(comment) + "</span>";
            continue;
        }

        // String literals: "...", '...', `...`
        if (c == "\"" || c == "'" || c == "`") {
            const quote = c;
            // A backtick string may span lines; the other two may not
            const stop = quote == "`" ? "\\" + quote : "\\" + quote + "\n";
            const start = i;
            i++;
            while (i < len) {
                i = code.spanUntil(i, stop);
                if (i >= len) {
                    break;
                }
                const sc = code.charAt(i);
                if (sc == "\\") {
                    i += 2;
                    continue;
                }
                if (sc == quote) {
                    i++;
                    break;
                }
                break;      // newline in a non-backtick string
            }
            const strVal = code.substring(start, i);
            out += "<span style=\"color: #032f62;\">" + htmlsc(strVal) + "</span>";
            continue;
        }

        // Number literals
        if (isDigit(c)) {
            const start = i;
            i = code.spanWhile(i, NUM_CHARS);
            const numVal = code.substring(start, i);
            out += "<span style=\"color: #005cc5;\">" + htmlsc(numVal) + "</span>";
            continue;
        }

        // Identifiers, Keywords, Builtins
        if (isAlpha(c)) {
            const start = i;
            i = code.spanWhile(i, IDENT_CHARS);
            const ident = code.substring(start, i);

            // Look ahead for function call: func(...)
            const lookAhead = code.spanWhile(i, SPACE_CHARS);
            const isCall = lookAhead < len && code.charAt(lookAhead) == "(";

            if (KEYWORDS.includes(ident)) {
                out += "<span style=\"color: #d73a49; font-weight: bold;\">" + htmlsc(ident) + "</span>";
            } else if (BUILTINS.includes(ident)) {
                out += "<span style=\"color: #6f42c1;\">" + htmlsc(ident) + "</span>";
            } else if (isCall) {
                out += "<span style=\"color: #005cc5;\">" + htmlsc(ident) + "</span>";
            } else {
                out += htmlsc(ident);
            }
            continue;
        }

        // Punctuation and whitespace, taken as a run rather than one at a time
        const plain = code.spanUntil(i + 1, TOKEN_START);
        out += htmlsc(code.substring(i, plain));
        i = plain;
    }

    return out;
}

function getCode(e) {
    if (e.body != "") return e.body;
    if (e.args.length > 0 && e.args[0] != "") return e.args[0];
    return "";
}

function plugin_highlight_convert(e) {
    const code = getCode(e);
    if (code == "") return "<p>(no code)</p>";

    const highlighted = highlightJs(code);
    return "<pre style=\"background-color: #f6f8fa; color: #24292e; border: 1px solid #e1e4e8; font-family: monospace; font-size: 13px;\"><code>" + highlighted + "</code></pre>";
}

```

使い方:
```js
#pkript(highlight){{
function hello(name) {
    console.log("Hello, " + name);
}
}}
```

## recent - 更新時刻付きページ一覧

ページの最終更新時刻と凍結状態を一覧表示します。wiki.time, wiki.isFrozen, date.format, str.padStart を活用する例です。

plugin/pkript/script/recent.js

```js
function plugin_recent_convert(e) {
    const prefix = e.args[0] || "";
    const pages = wiki.pages(prefix);
    if (pages.length == 0) return "<p>該当するページがありません。</p>";

    // 各ページの更新時刻と凍結状態を取得
    const items = pages.map((page) => {
        const t = wiki.time(page);
        return {
            page: page,
            time: t,
            frozen: wiki.isFrozen(page)
        };
    });

    // 更新時刻の新しい順にソート
    items.sort((a, b) => b.time - a.time);

    const rows = items.map((item, idx) => {
        const no = String(idx + 1).padStart(2, "0");
        const frozenMark = item.frozen ? " <i>(凍結)</i>" : "";
        const timeStr = date.format(item.time);
        return "<li>" + no + ". " + wiki.link(item.page) + " <small>[" + timeStr + "]</small>" + frozenMark + "</li>";
    }).join("\n");

    return "<ul>\n" + rows + "\n</ul>";
}
```

使い方:
```text
#recent(Docs/)
```

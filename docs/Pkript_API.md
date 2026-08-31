#author("2026-08-31T12:00:00+09:00","","")

# Pkript/API

組み込み関数とメソッドのリファレンス。

## グローバル関数

| 関数 | 説明 |
| --- | --- |
| htmlsc(str) | HTMLエスケープ |
| String(val) | 文字列に変換 |
| Number(val) | 数値に変換 |
| Boolean(val) | 真偽値に変換 |
| func_get_args() | 引数配列を返す |
| func_num_args() | 引数の個数を返す |
| func_get_arg(n) | n番目の引数を返す |
| is_page(page) | wiki.exists(page) の別名 |
| make_pagelink(page, [label]) | wiki.link(page, label) の別名 |
| convert_html(text) | wiki.convert(text) の別名 |
| strip_bracket(str) | wiki.stripBracket(str) の別名 |
| encode(str) / decode(str) | wiki.encode / decode の別名 |
| get_source(page) | wiki.source(page) の別名 |
| get_existpages([prefix]) | wiki.pages(prefix) の別名 |
| get_filetime(page) | wiki.time(page) の別名 |
| is_freeze(page) | wiki.isFrozen(page) の別名 |
| format_date(t, [paren]) | date.format(t, paren) の別名 |

## wiki

Wikiデータ操作API。

| メソッド | 説明 |
| --- | --- |
| wiki.exists(page) | ページの存在確認 |
| wiki.link(page, [label]) | ページへのリンクHTML生成 |
| wiki.convert(text) | WikiテキストをHTMLに変換 |
| wiki.source(page) | ページのWikiソースを返す |
| wiki.pages([prefix]) | ページ名一覧を配列で返す |
| wiki.stripBracket(str) | ページ名を囲む二重角括弧 [ ](%20.md) を除去 |
| wiki.encode(str) | ページ名を16進ファイル名表現に変換 |
| wiki.decode(str) | 16進表現からページ名に変換 |
| wiki.write(page, text) | ページを上書き保存（action専用） |
| wiki.append(page, text) | ページ末尾に追記保存（action専用） |
| wiki.token() | CSRF対策トークンを返す |
| wiki.canWrite(page) | 書き込み条件を確認（下記） |
| wiki.time(page) | ページの最終更新時刻（存在しない・読めないページは 0） |
| wiki.isFrozen(page) | ページが凍結されているか（存在しないページは false） |
| wiki.uri([page], [absolute]) | Wiki自身 / ページのURI（既定は相対、第2引数 true で絶対URI） |
| wiki.redirect(page) | リクエストを終了し page へ移動（action + POST のみ） |

### canWrite が確認するもの

wiki.canWrite(page) が確認するのは、スクリプトの信頼度・PKWK_READONLY・ページ名・凍結・$edit_auth_pages。action・POST・トークンは確認しない。

wiki.write は全条件を確認する。canWrite() が真でもトークンなしのPOSTは失敗する。

### wiki.redirect

```js
function plugin_guestbook_action(e) {
    wiki.append("GuestBook", "- " + e.vars["text"]);
    wiki.redirect("GuestBook");   // ここで終わる
}
```

wiki.redirect(page) はリクエストをその時点で終了し、page へリダイレクトする。戻り値はなく、以降のコードは実行されない。

- action かつ POST のみ呼び出し可能。
- 移動先はこのWikiのページに限定される。: で始まるページと存在しないページは拒否される。
- try / catch で捕捉できない。

### ページの書き込み例
```js
// フォーム出力 (convert)
return "<form method=\"post\">"
    + "<input type=\"hidden\" name=\"plugin\" value=\"pkript\">"
    + "<input type=\"hidden\" name=\"script\" value=\"guestbook\">"
    + "<input type=\"hidden\" name=\"pkript_token\" value=\"" + wiki.token() + "\">"
    + "<textarea name=\"text\"></textarea>"
    + "<input type=\"submit\" value=\"投稿\">"
    + "</form>";
```

```js
// 投稿処理 (action)
function plugin_guestbook_action(e) {
    wiki.append("GuestBook", "\n- " + e.vars["text"]);
    return "<p>投稿しました。</p>";
}
```

## data

リクエストをまたいで値を永続化するAPI。キー1つが :config/pkript/data/&lt;キー&gt; ページ1枚に対応し、値はJSONで保存される。

| メソッド | 説明 |
| --- | --- |
| data.get(key, [default]) | 値を返す。未設定の場合は default（省略時 null） |
| data.set(key, value) | 書き込み（action + POST + トークン + 信頼度が必要） |
| data.has(key) | キーが存在するか |
| data.remove(key) | 削除。存在しなかった場合は false |
| data.keys([prefix]) | キーの配列（ソート済み） |
| data.canWrite(key) | 書き込み条件を確認 |

```js
function plugin_counter_action(e) {
    const n = data.get("counter/" + e.vars["page"], 0) + 1;
    data.set("counter/" + e.vars["page"], n);
    wiki.redirect(e.vars["page"]);
}
```

- キーは [A-Za-z0-9_-] を / でつないだもの（128バイトまで）。接頭辞の外のページは指定できない。
- 書き込み条件は wiki.write と同じ。ページの描画中は書き込めない。
- 読み出しにアクセス制御はない。 どのスクリプトからもどのキーも読める。秘密データの保存には使用しないこと。
- PKRIPT_ALLOW_DATA を 0 にすると、読み出しは常に既定値を返し、書き込みは拒否される。

## url

| メソッド | 説明 |
| --- | --- |
| url.encode(str) | パーセントエンコード（RFC 3986。空白は %20） |
| url.decode(str) | パーセントデコード。UTF-8として無効なバイト列は空文字列 |

ページリンクには wiki.uri() を使う。url.encode はクエリ文字列の値を手動で組み立てる場合に使用する。

```js
return "<a href=\"" + htmlsc(wiki.uri() + "?cmd=pkript&script=v&q=" + url.encode(q)) + "\">検索</a>";
```

## date

時刻の取得と整形のAPI。

| メソッド | 説明 |
| --- | --- |
| date.now() | 現在時刻（エポック秒 - サーバTZ差） |
| date.format(t, [format]) | 時刻を整形して文字列化 |

date.format(wiki.time(page)) は #lastmod と同じ表示になる。

### 書式文字列
書式を省略または空文字にすると、$date_format / $time_format / $weeklabels の設定に従う。#lastmod 相当の表示を作る場合は省略を推奨する。

書式を指定する場合、以下の文字のみ解釈される（64バイトまで）。

```text
Y y m n d j H G h g i s D l N w M F a A U t L
```

- 上記以外の文字（日本語など）はそのまま出力される（例: "Y年n月j日"）。
- \ を前置すると次の1文字をリテラルとして扱う。
- タイムゾーン名（e T Z など）はホスト設定依存のため除外されている。

## JSON

JSONの生成と解析のAPI。

| メソッド | 説明 |
| --- | --- |
| JSON.stringify(val, [indent]) | 値をJSON文字列に変換 |
| JSON.parse(str) | JSON文字列をオブジェクト・配列・スカラーに解析 |

- オブジェクトは {}、配列は [] に変換される。空のオブジェクトも {} のまま出力される。
- 関数はJSON非対応。オブジェクト内では項目ごと除外、配列内では null になる（JavaScriptと同仕様）。
- 循環参照を含む値はエラー。
- UTF-8文字列は \uXXXX にエスケープされず素通しされる。
- indent に数値を指定するとその文字数分の空白でインデントされる（既定値 0 は1行）。
- 入れ子の深さは PKRIPT_MAX_DEPTH、サイズは PKRIPT_MAX_STRING / PKRIPT_MAX_ARRAY に従う。
- JSON.parse() は不正なJSONでエラーを投げる。try / catch で捕捉可能。

## console

デバッグ出力API。ページへの出力手段ではない。

| メソッド | 説明 |
| --- | --- |
| console.log(...) | ログを1行記録 |
| console.warn(...) | 警告として1行記録 |
| console.error(...) | エラー形式で1行記録（実行は継続） |

```text
console.log("count =", items.length, e.opts);
// Pkript Log: count = 3 {sort: "date"}
```

- 呼び出し時点では出力されない。スクリプト終了後、PKRIPT_DEBUG が有効なときのみ、戻り値HTMLの後ろにまとめて出力される。
- 引数は空白区切りで連結される。オブジェクトと配列は深さ3まで展開される（それ以上は {...} / [...]、循環は [circular]）。
- 出力はHTMLエスケープされる。インライン呼び出しでも段落構造を破壊しない。
- pkript-log / pkript-log-warn / pkript-error のクラスが付与される（スタイルは同梱していない）。
- 実行途中でエラーになった場合も、その時点までのログがエラーの前に出力される。
- PKRIPT_MAX_LOG 行 / PKRIPT_MAX_LOG_BYTES バイトを超えると記録を停止する。スクリプト自体は失敗しない。
- echo に相当する即時出力はない。エントリポイントの戻り値のみがサニタイザを経由して出力される。

## html

| メソッド | 説明 |
| --- | --- |
| html.escape(str) | HTMLエスケープ |
| html.br(str) | 改行文字を &lt;br /&gt; に変換 |
| html.strip(str) | HTMLタグを除去 |

## Math

| メソッド | 説明 |
| --- | --- |
| Math.floor(n) | 切り捨て |
| Math.ceil(n) | 切り上げ |
| Math.round(n) | 四捨五入 |
| Math.abs(n) | 絶対値 |
| Math.min(a, b, ...) | 最小値 |
| Math.max(a, b, ...) | 最大値 |
| Math.random() | 0以上1未満の乱数 |

## Object

| メソッド | 説明 |
| --- | --- |
| Object.keys(obj) | キー一覧を配列で返す |
| Object.values(obj) | 値一覧を配列で返す |
| Object.has(obj, key) | キーが存在するか |

## 配列 (Array)

| メソッド / プロパティ | 説明 |
| --- | --- |
| arr.length | 要素数 |
| arr.push(val) | 末尾に追加 |
| arr.pop() | 末尾を取り出す |
| arr.shift() | 先頭を取り出す |
| arr.unshift(val) | 先頭に追加 |
| arr.join(sep) | 連結して文字列化 |
| arr.indexOf(val) | 要素の位置を返す |
| arr.includes(val) | 要素が存在するか |
| arr.slice(start, [end]) | 部分配列を返す |
| arr.reverse() | 順序を反転 |
| arr.concat(other) | 配列を結合 |
| arr.map(fn) | 要素を変換した新しい配列を返す |
| arr.filter(fn) | 条件に合う要素の新しい配列を返す |
| arr.find(fn) | 条件に合う最初の要素を返す |
| arr.findIndex(fn) | 条件に合う最初の要素のインデックスを返す |
| arr.forEach(fn) | 全要素にコールバックを適用（戻り値なし） |
| arr.some(fn) | 1つでも条件に合えば true |
| arr.every(fn) | すべて条件に合えば true |
| arr.reduce(fn, [initial]) | 1つの値に畳み込む |
| arr.sort([compareFn]) | 並べ替え（破壊的） |

```js
const nums = [1, 2, 3, 4, 5];
const evens = nums.filter((x) => x % 2 == 0);     // [2, 4]
const doubled = nums.map((x) => x * 2);            // [2, 4, 6, 8, 10]
const sorted = [10, 9, 1].sort((a, b) => a - b);   // [1, 9, 10]
```

### コールバックを受け取るメソッド

map / filter / find / findIndex / forEach / some / every のコールバックは (item, index, array) を受け取る。走査は配列のコピーに対して行われるため、コールバック内で元の配列を変更してもループ回数は変わらない。

- some は最初に真になった時点で、every は最初に偽になった時点で打ち切る。
- 空の配列に対して some は false、every は true（JavaScriptと同仕様）。

### 畳み込み (reduce)

reduce のコールバックのシグネチャは (acc, item, index, array)。

```js
const total = rows.reduce((a, r) => a + r.count, 0);
const index = keys.reduce((acc, k) => { acc[k] = 1; return acc; }, {});
```

初期値を省略すると先頭要素が初期値になり走査は2番目から始まる。空の配列で初期値を省略した場合はエラー（JavaScriptで TypeError が投げられる場面と同じ）。

## 文字列 (String)

UTF-8マルチバイト対応。長さのカウント、インデックス指定はすべて文字数（コードポイント数）単位。

| メソッド / プロパティ | 説明 |
| --- | --- |
| str.length | 文字数 |
| str.toUpperCase() | 大文字化 |
| str.toLowerCase() | 小文字化 |
| str.trim() | 前後の空白を除去 |
| str.trimStart() | 先頭の空白を除去 |
| str.trimEnd() | 末尾の空白を除去 |
| str.indexOf(sub, [from]) | 部分一致の位置を返す（見つからない場合は -1） |
| str.lastIndexOf(sub) | 後方から部分一致の位置を返す |
| str.includes(sub) | 部分一致するか |
| str.startsWith(sub) | 前方一致するか |
| str.endsWith(sub) | 後方一致するか |
| str.replace(from, to) | 最初の一致を置換 |
| str.replaceAll(from, to) | すべての一致を置換 |
| str.split(sep) | 分割して配列化 |
| str.substring(start, [end]) | 部分文字列を返す |
| str.slice(start, [end]) | 部分文字列を返す |
| str.charAt(index) | 指定位置の1文字を返す |
| str.at(index) | 指定位置の1文字を返す（負の添字対応） |
| str.padStart(len, [pad]) | 先頭を pad で埋めて len 文字にする |
| str.padEnd(len, [pad]) | 末尾を pad で埋めて len 文字にする |
| str.repeat(count) | 指定回数繰り返した文字列を返す |
| str.match(re) | 正規表現で照合（[Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md)） |
| str.matchAll(re) | 正規表現で全件照合 |
| str.search(re) | 正規表現に最初に一致した位置を返す |
| str.spanWhile(from, set) | set に含まれる文字が続く間だけ進み、止まった位置を返す |
| str.spanUntil(from, set) | set に含まれない文字が続く間だけ進み、止まった位置を返す |

### padStart / padEnd
pad（省略時は半角空白）を繰り返して先頭または末尾に埋め、len 文字の文字列にする。すでに len 以上の場合はそのまま返す。PKRIPT_MAX_STRING を超える要求はメモリ確保前にエラーになる。

```js
const cells = rows.map((r) => r.no.padStart(3, "0") + " " + r.name);
```

### at
str.at(i) は負のインデックスを受け付ける。-1 は最後の文字を指す。範囲外は空文字列を返す。

### spanWhile / spanUntil

文字クラスに一致する（または一致しない）間だけ進み、停止位置を返す。走査系のスクリプトで1文字ずつループする代わりに使う。

```js
const IDENT = "a-zA-Z0-9_$";
let i = 0;
while (i < code.length) {
    const end = code.spanWhile(i, IDENT);
    if (end > i) {
        out += htmlsc(code.substring(i, end));
        i = end;
        continue;
    }
    out += htmlsc(code.charAt(i));
    i++;
}
```

- set には a-z のような範囲を書ける。- 自体を含める場合は先頭か末尾に置く。
- 範囲として解釈されるのは昇順のときだけ。z-a は3文字の集合になる。
- 戻り値は停止位置なので、そのまま substring() に渡せる。
- マルチバイトでも charAt / substring と同じ文字数単位で動作する。

## 正規表現 (RegExp)

/パターン/フラグ で生成する。詳細は [Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md)。

| メソッド | 説明 |
| --- | --- |
| re.test(str) | 一致するか |
| re.exec(str) | 最初の一致の配列（全体, グループ1, ...）。一致しない場合は null |
| re.source() | パターン文字列を返す |
| re.flags() | フラグ文字列を返す |
| re.global() | g フラグが付いているか |

文字列側の match / matchAll / search / replace / replaceAll / split も正規表現を受け取る。

```js
const DATE = /(\d{4})-(\d{2})/;
return "2024-05".replace(DATE, "$1年$2月");   // 2024年05月
```

## 数値 (Number)

| メソッド | 説明 |
| --- | --- |
| num.toFixed(digits) | 指定桁数の小数文字列に変換 |
| num.toString() | 文字列に変換 |

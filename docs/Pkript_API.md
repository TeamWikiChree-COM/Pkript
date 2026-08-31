# Pkript/API

組み込み関数とメソッドのリファレンスです。

## グローバル関数

| 関数 | 説明 |
| --- | --- |
| htmlsc(str) | HTMLエスケープ |
| String(val) | 文字列に変換 |
| Number(val) | 数値に変換 |
| Boolean(val) | 真偽値に変換 |
| func_get_args() | 引数配列を取得 |
| func_num_args() | 引数の個数を取得 |
| func_get_arg(n) | n番目の引数を取得 |
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

Wikiのデータ操作APIです。

| メソッド | 説明 |
| --- | --- |
| wiki.exists(page) | ページの存在確認 |
| wiki.link(page, [label]) | ページへのリンクHTML生成 |
| wiki.convert(text) | WikiテキストをHTMLに変換 |
| wiki.source(page) | ページのWikiソースを取得 |
| wiki.pages([prefix]) | ページ名一覧を配列で取得 |
| wiki.stripBracket(str) | ページ名を囲む二重角括弧 [ ](%20.md) を除去 |
| wiki.encode(str) | ページ名を16進ファイル名表現に変換 |
| wiki.decode(str) | 16進表現からページ名に変換 |
| wiki.write(page, text) | ページを上書き保存（action専用） |
| wiki.append(page, text) | ページ末尾に追記保存（action専用） |
| wiki.token() | CSRF対策トークンを取得 |
| wiki.canWrite(page) | 書き込み可能か確認 |
| wiki.time(page) | ページの最終更新時刻（存在しない・読めないページは 0） |
| wiki.isFrozen(page) | ページが凍結されているか確認（存在しないページは false） |
| wiki.uri([page], [absolute]) | Wiki自身 / ページのURI（既定は相対、第2引数 true で絶対URI） |

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

## date

時刻の取得と整形のAPIです。

| メソッド | 説明 |
| --- | --- |
| date.now() | 現在時刻を取得（エポック秒 - サーバTZ差） |
| date.format(t, [format]) | 時刻を整形して文字列化 |

### 時刻の持ち方
PukiWikiは時刻を「エポック秒 − サーバのタイムゾーン差」で保持し、表示時に ZONETIME を足します。
wiki.time() と date.now() はその値をそのまま返し、date.format() は同じ規則で解釈します。
そのため、date.format(wiki.time(page)) は PukiWiki 本体の #lastmod と同じ表示になります。

### 書式の指定
書式を省略（または空文字）にした場合は本体の format_date() に委譲され、$date_format / $time_format / $weeklabels の設定がそのまま反映されます。#lastmod 系の表示を作成する場合は省略を推奨します。

書式を指定する場合、PHP の date() で許可された以下の文字のみを解釈します（64バイトまで）。

```text
Y y m n d j H G h g i s D l N w M F a A U t L
```

- 上記以外の文字（日本語など）はそのまま出力されます（例: "Y年n月j日"）。
- \ を前置すると、次の1文字をリテラルとして扱います。
- タイムゾーン名（e T Z など）はホスト設定依存を防ぐため除外されています。
- 第2引数に真偽値（true）を渡す format_date(t, true) の括弧付きスタイルも動作します。

## JSON

JSONの生成と解析を行うAPIです。

| メソッド | 説明 |
| --- | --- |
| JSON.stringify(val, [indent]) | 値をJSON文字列に変換 |
| JSON.parse(str) | JSON文字列をオブジェクト・配列・スカラーに解析 |

- オブジェクトは {}、配列は [] に変換されます。空のオブジェクトも {} のまま出力されます。
- 関数はJSONにならないため、オブジェクト内では項目ごと除外され、配列内では null になります（JavaScriptの動作と同様）。
- 循環参照を含む値はエラーになります。
- UTF-8文字列は \uXXXX にエスケープされず素通しされます。
- indent に数値を指定すると、その文字数分の空白でインデント（整形）されます（既定値 0 は1行）。
- 入れ子の深さは PKRIPT_MAX_DEPTH、サイズは PKRIPT_MAX_STRING / PKRIPT_MAX_ARRAY の上限に従います。
- JSON.parse() は不正なJSONでエラーを投げます。try / catch で捕捉可能です。

## console

デバッグ用の出力です。ページへの出力手段ではありません。

| メソッド | 説明 |
| --- | --- |
| console.log(...) | ログを1行残す |
| console.warn(...) | 警告として1行残す |
| console.error(...) | エラーと同じ見た目で1行残す（実行は止まりません） |

```text
console.log("count =", items.length, e.opts);
// Pkript Log: count = 3 {sort: "date"}
```

- 呼んだ時点では何も出力されません。スクリプトが終わったあと、PKRIPT_DEBUG が有効なときだけ、戻り値のHTMLの**後ろ**にまとめて表示されます。
- 引数は空白区切りで連結されます。オブジェクトと配列は中身が展開されます（深さ3まで。それより深いものは {...} / [...]、循環は [circular]）。
- 内容はHTMLエスケープされます。エラー表示と同じ1行の形なので、インライン呼び出しでも段落は壊れません。
- 専用のCSSは同梱していません。「Pkript Log:」というラベルで読めるようにしてあり、pkript-log / pkript-log-warn / pkript-error のクラスは色を付けたい場合の目印です。
- 実行が途中でエラーになった場合も、そこまでのログがエラーの前に出ます。
- 行数は PKRIPT_MAX_LOG、全体のバイト数は PKRIPT_MAX_LOG_BYTES が上限です。超えるとそこで記録を止めますが、**スクリプトは失敗しません**。
- 即時出力する echo はありません。出力はエントリポイントの戻り値だけがサニタイザを通る仕組みのためで、echo すると戻り値より前に出てしまい位置も合いません。

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
| Math.random() | 0以上1未満の乱数を取得 |

## Object

| メソッド | 説明 |
| --- | --- |
| Object.keys(obj) | キー一覧を配列で取得 |
| Object.values(obj) | 値一覧を配列で取得 |
| Object.has(obj, key) | キーが存在するか確認 |

## 配列 (Array)

| メソッド / プロパティ | 説明 |
| --- | --- |
| arr.length | 要素数 |
| arr.push(val) | 末尾に追加 |
| arr.pop() | 末尾を取り出し |
| arr.shift() | 先頭を取り出し |
| arr.unshift(val) | 先頭に追加 |
| arr.join(sep) | 連結して文字列化 |
| arr.indexOf(val) | 要素の位置を検索 |
| arr.includes(val) | 要素の存在確認 |
| arr.slice(start, [end]) | 部分配列を抽出 |
| arr.reverse() | 順序を反転 |
| arr.concat(other) | 配列を結合 |
| arr.map(fn) | 要素を変換 |
| arr.filter(fn) | 要素を絞り込み |
| arr.find(fn) | 条件に合う最初の要素 |
| arr.findIndex(fn) | 条件に合う最初の位置 |
| arr.forEach(fn) | 全要素にコールバックを適用（戻り値なし） |
| arr.some(fn) | 1つでも条件に合えば true |
| arr.every(fn) | すべて条件に合えば true |
| arr.reduce(fn, [initial]) | 1つの値に畳み込む |
| arr.sort([compareFn]) | 並べ替え |

```js
const nums = [1, 2, 3, 4, 5];
const evens = nums.filter((x) => x % 2 == 0);     // [2, 4]
const doubled = nums.map((x) => x * 2);            // [2, 4, 6, 8, 10]
const sorted = [10, 9, 1].sort((a, b) => a - b);   // [1, 9, 10]
```

### コールバックを受け取るメソッド

map / filter / find / findIndex / forEach / some / every のコールバックは、JavaScript と同じく (item, index, array) を受け取ります。走査するのは配列のコピーなので、コールバックが元の配列に push してもループは伸びません。

- some は真になった時点で、every は偽になった時点で走査を打ち切ります。
- 空の配列に対しては some が false、every が true になります（JavaScript と同じ）。

### 畳み込み (reduce)

reduce のコールバックだけは形が違い、(acc, item, index, array) を受け取ります。

```js
const total = rows.reduce((a, r) => a + r.count, 0);
const index = keys.reduce((acc, k) => { acc[k] = 1; return acc; }, {});
```

初期値を省略すると先頭の要素が初期値になり、走査は2番目から始まります。このとき空の配列は返す値を持てないためエラーになります（JavaScript が TypeError を投げる場面です）。

## 文字列 (String)

マルチバイト（UTF-8）に対応しています。

| メソッド / プロパティ | 説明 |
| --- | --- |
| str.length | 文字数 |
| str.toUpperCase() | 大文字化 |
| str.toLowerCase() | 小文字化 |
| str.trim() | 前後の空白除去 |
| str.trimStart() | 先頭の空白除去 |
| str.trimEnd() | 末尾の空白除去 |
| str.indexOf(sub, [from]) | 部分一致の位置検索 |
| str.lastIndexOf(sub) | 後方から部分一致の位置検索 |
| str.includes(sub) | 部分一致の確認 |
| str.startsWith(sub) | 前方一致の確認 |
| str.endsWith(sub) | 後方一致の確認 |
| str.replace(from, to) | 置換（最初の一致） |
| str.replaceAll(from, to) | 置換（すべての一致） |
| str.split(sep) | 分割して配列化 |
| str.substring(start, [end]) | 部分文字列を取得 |
| str.slice(start, [end]) | 部分文字列を取得 |
| str.charAt(index) | 指定位置の1文字 |
| str.at(index) | 指定位置の1文字（負の添字対応） |
| str.padStart(len, [pad]) | 文字数が len になるまで先頭を埋める |
| str.padEnd(len, [pad]) | 文字数が len になるまで末尾を埋める |
| str.repeat(count) | 指定回数繰り返し |
| str.match(re) | 正規表現で照合（[Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md)） |
| str.matchAll(re) | 正規表現で全件照合 |
| str.search(re) | 正規表現に最初に一致した位置 |
| str.spanWhile(from, set) | set に含まれる文字が続く間だけ進み、止まった位置を返す |
| str.spanUntil(from, set) | set に含まれない文字が続く間だけ進み、止まった位置を返す |

### 文字列のパディング (padStart / padEnd)
文字数が len になるまで pad 文字列（既定値は半角空白）を繰り返して埋めます。マルチバイトを文字数として正しく数え、すでに len 以上の文字列はそのまま返します。長すぎる要求（PKRIPT_MAX_STRING 超過）はメモリ確保前に弾かれます。

```js
const cells = rows.map((r) => r.no.padStart(3, "0") + " " + r.name);
```

### 負の添字アクセス (at)
str.at(i) は負の数を受け付けます。-1 は最後の1文字を指します。範囲外は空文字列を返します。

### 文字列の走査 (spanWhile / spanUntil)

1文字ずつ回るループの代わりに使います。構文ハイライタのような走査系のスクリプトでは、1文字あたり50〜60ステップかかるループが1回の呼び出しになります。

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

- set には a-z のような範囲を書けます。- 自体を入れるときは先頭か末尾に置いてください。
- 範囲として扱うのは昇順のときだけです。z-a は3文字の集合になります。
- 戻り値は止まった位置なので、そのまま substring() に渡せます。
- マルチバイトでも charAt や substring と同じ数え方です。

## 正規表現 (RegExp)

/パターン/フラグ で作ります。詳しくは [Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md) を参照してください。

| メソッド | 説明 |
| --- | --- |
| re.test(str) | 一致するかどうか |
| re.exec(str) | 最初の一致の配列（全体, グループ1, ...）。無ければ null |
| re.source() | 書いたパターン |
| re.flags() | 書いたフラグ |
| re.global() | g フラグが付いているか |

文字列側の match / matchAll / search / replace / replaceAll / split も正規表現を受け取ります。

```js
const DATE = /(\d{4})-(\d{2})/;
return "2024-05".replace(DATE, "$1年$2月");   // 2024年05月
```

## 数値 (Number)

| メソッド | 説明 |
| --- | --- |
| num.toFixed(digits) | 指定桁数の小数文字列に変換 |
| num.toString() | 文字列に変換 |

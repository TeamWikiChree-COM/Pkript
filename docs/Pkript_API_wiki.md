# Pkript/API/wiki

WikiデータへのアクセスAPI。ページの読み書きと存在確認、URI生成、リダイレクトを提供する。

## リファレンス

### wiki.exists(page)

```text
wiki.exists(page)
```

- page: String: 確認対象のページ名

戻り値: Boolean

### wiki.link(page, [label])

```text
wiki.link(page, [label])
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| page | String | リンク先のページ名 |
| label | String | リンクテキスト（省略時はページ名） |

戻り値: String: PukiWikiのページリンクHTML

### wiki.convert(text)

```text
wiki.convert(text)
```

- text: String: 変換対象のWikiテキスト

戻り値: String: 変換済みのHTML

1リクエストあたりの呼び出し回数上限: PKRIPT_MAX_CONVERT（既定 32）

### wiki.source(page)

```text
wiki.source(page)
```

- page: String: 対象ページ名

戻り値: String: Wikiソース。存在しないページは空文字列。

### wiki.pages([prefix])

```text
wiki.pages([prefix])
```

- prefix: String: 絞り込みに使うページ名の接頭辞（省略可）

戻り値: Array&lt;String&gt;: ページ名の配列。最大 PKRIPT_MAX_PAGES 件（既定 1000）

### wiki.write(page, text)

```text
wiki.write(page, text)
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| page | String | 書き込み対象のページ名 |
| text | String | 書き込む内容（ページ全体を上書き） |

戻り値: なし

呼び出し条件: action + POST + wiki.token() のトークン + スクリプトの信頼度。
書き込めるページの最大バイト数: PKRIPT_MAX_PAGE_BYTES（既定 512KB）。
1リクエストあたりの書き込み回数上限: PKRIPT_MAX_WRITES（既定 4）。

### wiki.append(page, text)

```text
wiki.append(page, text)
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| page | String | 書き込み対象のページ名 |
| text | String | 追記する内容（ページ末尾に追加） |

戻り値: なし

呼び出し条件と上限は wiki.write と同じ。

### wiki.token()

```text
wiki.token()
```

戻り値: String: CSRF対策トークン

トークンはログイン中のユーザーに紐付く。認証なしのWikiではサイト共通の値になる。

### wiki.canWrite(page)

```text
wiki.canWrite(page)
```

- page: String: 確認対象のページ名

戻り値: Boolean

確認する条件: スクリプトの信頼度 / PKWK_READONLY / ページ名 / 凍結状態 / $edit_auth_pages

確認しない条件: action / POST / トークン（フォーム描画中は常に偽のため）

wiki.write はすべての条件を確認する。canWrite() が true でもトークンなしのPOSTは失敗する。

### wiki.time(page)

```text
wiki.time(page)
```

- page: String: 対象ページ名

戻り値: Number: 最終更新時刻（エポック秒 - サーバTZ差）。存在しないページと読めないページは 0。

### wiki.isFrozen(page)

```text
wiki.isFrozen(page)
```

- page: String: 対象ページ名

戻り値: Boolean。存在しないページは false。

### wiki.uri([page], [absolute])

```text
wiki.uri([page], [absolute])
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| page | String | 対象ページ名（省略時はWiki自身のURI） |
| absolute | Boolean | true で絶対URI（省略または false で相対URI） |

戻り値: String

### wiki.redirect(page)

```text
wiki.redirect(page)
```

- page: String: 移動先のページ名

戻り値: なし（リクエストはその時点で終了し、以降のコードは実行されない）

呼び出し条件: action かつ POST のみ。
移動先は自Wikiのページに限定される。: で始まるページと存在しないページは拒否される。
try / catch で捕捉できない。

### wiki.stripBracket(str) / wiki.encode(str) / wiki.decode(str)

| メソッド | 戻り値 | 説明 |
| --- | --- | --- |
| wiki.stripBracket(str) | String | ページ名を囲む二重角括弧 [ ](%20.md) を除去 |
| wiki.encode(str) | String | ページ名をPukiWikiの16進ファイル名表現に変換 |
| wiki.decode(str) | String | 16進表現からページ名に変換 |

## 使用法

### フォームからページへ書き込む

フォームを描画（convert）し、送信された内容をページに追記する（action）。

```js
function plugin_guestbook_convert(e) {
    if (!wiki.canWrite("GuestBook")) return "<p>書き込み権限がありません</p>";
    return "<form method=\"post\">"
        + "<input type=\"hidden\" name=\"plugin\" value=\"pkript\">"
        + "<input type=\"hidden\" name=\"script\" value=\"guestbook\">"
        + "<input type=\"hidden\" name=\"pkript_token\" value=\"" + wiki.token() + "\">"
        + "<textarea name=\"text\"></textarea>"
        + "<input type=\"submit\" value=\"投稿\">"
        + "</form>";
}

function plugin_guestbook_action(e) {
    wiki.append("GuestBook", "\n- " + e.vars["text"]);
    wiki.redirect("GuestBook");
}
```

### ページ一覧からリンクを生成する

```js
const pages = wiki.pages("Blog/");
const links = pages.map((p) => "<li>" + wiki.link(p) + "</li>").join("");
return "<ul>" + links + "</ul>";
```

### Wikiテキストを動的に変換する

```js
const src = wiki.source("Template");
const html = wiki.convert(src.replace("__NAME__", htmlsc(e.args[0])));
return html;
```

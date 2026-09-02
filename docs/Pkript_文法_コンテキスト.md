# Pkript/文法/コンテキスト

エントリポイントの第1引数として渡されるコンテキストオブジェクト。

## リファレンス

### プロパティ一覧

| プロパティ | 型 | 説明 |
| --- | --- | --- |
| e.args | Array&lt;String&gt; | プラグイン呼び出し時に渡された位置引数の配列 |
| e.opts | Object | key=value 形式で渡された名前付きオプション |
| e.body | String | 複数行ブロック記法の本文 |
| e.page | String | 呼び出し元のページ名 |
| e.name | String | 実行中のスクリプト名 |
| e.type | String | 呼び出し種別（"convert" / "inline" / "action"） |
| e.vars | Object | GET / POST のフォーム送信値（機密キーは除外） |
| e.user | Object | 閲覧ユーザーの情報（下記） |
| e.method | String | HTTPメソッド（"GET" / "POST"） |

- e.opts: #pkript(list, foo, bar, class=fruit) と渡すと e.opts.class で "fruit" が取れる
- e.vars: パスワード等の機密キーは自動で除外される

### e.user

閲覧中のユーザー情報を保持するオブジェクト。プロパティは常に存在する。

| プロパティ | 型 | 説明 |
| --- | --- | --- |
| e.user.name | String | ログインユーザー名。未ログイン時は空文字列 |
| e.user.fullname | String | ユーザー表示名。未ログイン時は空文字列 |
| e.user.groups | Array&lt;String&gt; | 所属グループ一覧。ユーザー名自身と valid-user を含む |

- 存在しないプロパティへのアクセスはエラーになるため、未ログイン状態は null ではなく空文字列や空配列で表す
- groups には $auth_groups で解決したグループのほか、ユーザー名自身と valid-user が含まれる。ログイン中は空配列にならない
- fullname が name と異なる値になるのは、AUTH_TYPE_EXTERNAL / AUTH_TYPE_SAML で $ldap_user_account を有効にしLDAPから表示名を取得している場合。BASIC認証とフォーム認証では name と同じ値になる
- 管理者判定は取得できない。PukiWiki に管理者という概念はなく、pkwk_login() はパスワードの比較のみで結果を保持しない。権限で分岐する場合は wiki.canWrite(page) か e.user.groups を使う
- 閲覧者ごとに出力が変わるページになる。リバースプロキシやブラウザキャッシュを挟む構成では注意が必要

## 使用法

### 引数とオプションを処理する

```js
function plugin_list_convert(e) {
    const limit = Number(e.opts.limit || 10);
    const prefix = e.args[0] || "";
    const pages = wiki.pages(prefix).slice(0, limit);
    return "<ul>" + pages.map((p) => "<li>" + wiki.link(p) + "</li>").join("") + "</ul>";
}
```

### ログイン状態に応じて出力を切り替える

```js
function plugin_mypage_convert(e) {
    if (e.user.name == "") {
        return "<p>ログインしてください。</p>";
    }
    return "<p>ようこそ、" + htmlsc(e.user.fullname || e.user.name) + " さん</p>";
}
```

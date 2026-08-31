# Pkript/リファレンス/文法/コンテキスト

エントリポイントの第1引数として渡されるコンテキストオブジェクト（e）の仕様。

## リファレンス

### プロパティ一覧

| プロパティ | 型 | 説明 |
| --- | --- | --- |
| e.args | Array&lt;String&gt; | プラグイン呼び出し時に渡された位置引数の配列 |
| e.opts | Object | key=value 形式で渡された名前付きオプション |
| e.body | String | 複数行ブロック記法 {{ ... }} 内のテキスト |
| e.page | String | 呼び出し元のページ名 |
| e.name | String | 実行中のスクリプト名 |
| e.type | String | 呼び出し種別（"convert" / "inline" / "action"） |
| e.vars | Object | GET / POST のフォーム送信値（機密キーは除外） |
| e.user | Object | 閲覧ユーザーの情報（下記） |
| e.method | String | HTTPメソッド（"GET" / "POST"） |

### e.user

閲覧中のユーザー情報を保持するオブジェクト。プロパティは常に存在する。

| プロパティ | 型 | 説明 |
| --- | --- | --- |
| e.user.name | String | ログインユーザー名。未ログイン時は空文字列 |
| e.user.fullname | String | ユーザー表示名。未ログイン時は空文字列 |
| e.user.groups | Array&lt;String&gt; | 所属グループ一覧。ユーザー名自身と valid-user を含む |

- 管理者かどうかの直接フラグは存在しない。権限判定は wiki.canWrite(page) または e.user.groups を用いる

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

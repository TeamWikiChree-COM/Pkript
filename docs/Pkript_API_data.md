# Pkript/API/data

リクエストをまたいで値を永続化するAPI。キー1つが :config/pkript/data/&lt;キー&gt; ページ1枚に対応し、値はJSONで保存される。

## リファレンス

### data.get(key, [default])

```text
data.get(key, [default])
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| key | String | 取得対象のキー |
| default | any | キーが存在しない場合の戻り値（省略時 null） |

戻り値: 保存された値、またはデフォルト値

読み出しにアクセス制御はない。どのスクリプトからもどのキーも読める。
PKRIPT_ALLOW_DATA が 0 の場合は常にデフォルト値を返す。

### data.set(key, value)

```text
data.set(key, value)
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| key | String | 書き込み対象のキー |
| value | any | 保存する値（JSON化して保存される） |

戻り値: なし

呼び出し条件: action + POST + wiki.token() のトークン + スクリプトの信頼度（wiki.write と同じ）。ページの描画中は書き込めない。
PKRIPT_ALLOW_DATA が 0 の場合は拒否される。

### data.has(key)

```text
data.has(key)
```

- key: String

戻り値: Boolean: キーが存在するか

### data.remove(key)

```text
data.remove(key)
```

- key: String

戻り値: Boolean: 削除した場合 true、存在しなかった場合 false

呼び出し条件は data.set と同じ。

### data.keys([prefix])

```text
data.keys([prefix])
```

- prefix: String: 絞り込みに使うキーの接頭辞（省略可）

戻り値: Array&lt;String&gt;: キーの配列（辞書順ソート済み）

### data.canWrite(key)

```text
data.canWrite(key)
```

- key: String

戻り値: Boolean

action / POST / トークンは確認しない（フォーム描画中は常に偽のため）。

### キーの制約

- 使用できる文字: [A-Za-z0-9_-] を / でつないだもの
- 最大バイト数: 128バイト
- 接頭辞（PKRIPT_DATA_PREFIX）の外のページは指定できない

## 使用法

### カウンターを実装する

```js
function plugin_counter_action(e) {
    const page = e.vars["page"];
    const n = data.get("counter/" + page, 0) + 1;
    data.set("counter/" + page, n);
    wiki.redirect(page);
}
```

### 書き込めるときだけフォームを表示する

```js
function plugin_vote_convert(e) {
    if (!data.canWrite("vote/" + e.args[0])) return "<p>投票は締め切られています</p>";
    return "<form method=\"post\">...</form>";
}
```

### キー一覧を取得して集計する

```js
const keys = data.keys("counter/");
const rows = keys.map((k) => "<tr><td>" + htmlsc(k) + "</td><td>" + data.get(k, 0) + "</td></tr>").join("");
return "<table>" + rows + "</table>";
```

## 注意点

秘密データの保存には使用しないこと。data.get() はアクセス制御なしで誰でも読める。

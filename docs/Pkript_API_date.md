# Pkript/API/date

時刻の取得と整形のAPI。

## リファレンス

### date.now()

```text
date.now()
```

戻り値: Number: 現在時刻（エポック秒 - サーバTZ差）。wiki.time() と同じ表現。

### date.format(t, [format])

```text
date.format(t, [format])
```

| 引数 | 型 | 説明 |
| --- | --- | --- |
| t | Number | フォーマット対象の時刻（date.now() / wiki.time() が返す値） |
| format | String | 書式文字列（省略時は $date_format / $time_format / $weeklabels に従う） |

戻り値: String

書式文字列で使える文字（64バイトまで）:

```text
Y y m n d j H G h g i s D l N w M F a A U t L
```

- 上記以外の文字はそのまま出力される（例: "Y年n月j日"）
- \ を前置すると次の1文字をリテラルとして扱う
- タイムゾーン名（e T Z など）はホスト設定依存のため除外されている

## 使用法

### 現在時刻を表示する

書式を省略すると #lastmod 相当の表示になる。

```js
return "<p>" + date.format(date.now()) + "</p>";
```

### ページの更新日時を表示する

```js
return date.format(wiki.time("FrontPage"));
```

### 独自フォーマットで表示する

```js
return date.format(date.now(), "Y年n月j日 H:i");
```

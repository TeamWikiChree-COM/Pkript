#author("2026-08-31T21:40:00+09:00","","")

# Pkript/リファレンス/API/url

URLエンコード・デコードのAPI。

## リファレンス

### url.encode(str)

```text
url.encode(str)
```

- str: String

戻り値: String: RFC 3986 準拠のパーセントエンコード。空白は %20。

ページリンクには wiki.uri() を使う。url.encode はクエリ文字列の値を手動で組み立てる場合に使用する。

### url.decode(str)

```text
url.decode(str)
```

- str: String

戻り値: String: UTF-8として無効なバイト列は空文字列。

## 使用法

### クエリ文字列を含むリンクを生成する

```js
const href = wiki.uri() + "?cmd=pkript&script=search&q=" + url.encode(q);
return "<a href=\"" + htmlsc(href) + "\">検索</a>";
```

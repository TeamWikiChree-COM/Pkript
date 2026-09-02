# Pkript/API

組み込み関数とオブジェクトのリファレンス。詳細は各ページへ。

## ページ一覧

- [Pkript/API/グローバル関数](Pkript_API_%E3%82%B0%E3%83%AD%E3%83%BC%E3%83%90%E3%83%AB%E9%96%A2%E6%95%B0.md): htmlsc, String, Number, Boolean, parseInt, parseFloat, isNaN, isFinite, NaN, Infinity, func_get_args, PHPエイリアス
- [Pkript/API/wiki](Pkript_API_wiki.md): exists, link, convert, source, pages, write, append, token, canWrite, time, isFrozen, uri, redirect
- [Pkript/API/data](Pkript_API_data.md): get, set, has, remove, keys, canWrite
- [Pkript/API/url](Pkript_API_url.md): encode, decode
- [Pkript/API/date](Pkript_API_date.md): now, format
- [Pkript/API/JSON](Pkript_API_JSON.md): stringify, parse
- [Pkript/API/console](Pkript_API_console.md): log, warn, error
- [Pkript/API/html](Pkript_API_html.md): escape, br, strip
- [Pkript/API/Math](Pkript_API_Math.md): floor, ceil, round, trunc, abs, sign, min, max, random, sqrt, pow, hypot, exp, log, 三角関数, PI, E
- [Pkript/API/Object](Pkript_API_Object.md): keys, values, entries, has, assign, fromEntries
- [Pkript/API/Array](Pkript_API_Array.md): length, push/pop, shift/unshift, at, map, filter, find, reduce, sort, flat, splice ほか
- [Pkript/API/String](Pkript_API_String.md): length, indexOf, includes, replace, split, substring, padStart, charCodeAt, spanWhile ほか
- [Pkript/API/RegExp](Pkript_API_RegExp.md): test, exec, source, flags, global
- [Pkript/API/Number](Pkript_API_Number.md): toFixed, toPrecision, toString, valueOf

## 概要

PHPの関数は呼べない。使えるのは以下のオブジェクトと、グローバルに置かれた少数の関数だけ。

| 分類 | 入り口 | できること |
| --- | --- | --- |
| Wikiデータ | wiki | ページの読み書き、存在確認、リンクとURI生成、Wiki記法の変換 |
| 永続データ | data | リクエストをまたぐキーと値の保存 |
| 時刻 | date | 現在時刻の取得と書式整形 |
| 変換 | JSON, url, html | JSONの相互変換、パーセントエンコード、HTMLエスケープ |
| デバッグ | console | PKRIPT_DEBUG が有効なときだけ出力されるログ |
| 計算 | Math, Number | 数値計算と数値の文字列化 |
| データ操作 | Array, String, Object, RegExp | 値そのものが持つメソッド |

```js
function plugin_recent_convert(e) {
    const pages = wiki.pages("Blog/");
    const sorted = pages.slice().sort((a, b) => wiki.time(b) - wiki.time(a));
    return "<ul>" + sorted.map((p) => "<li>" + wiki.link(p) + "</li>").join("") + "</ul>";
}
```

書き込み系（wiki.write, wiki.append, data.set, wiki.redirect）は action かつ POST、さらにトークンと信頼度を要求する。読み出し系に制限はない。

## 関連

- [Pkript/文法](Pkript_%E6%96%87%E6%B3%95.md) - 言語仕様
- [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) - 設定定数と上限値
- [Pkript/サンプル](Pkript_%E3%82%B5%E3%83%B3%E3%83%97%E3%83%AB.md) - 実用スクリプト集
- [Pkript/エラー一覧](Pkript_%E3%82%A8%E3%83%A9%E3%83%BC%E4%B8%80%E8%A6%A7.md) - エラーメッセージと対処法

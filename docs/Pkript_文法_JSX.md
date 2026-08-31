# Pkript/文法/JSX

HTMLをそのまま式として書ける記法です。文字列連結の代わりに使います。

```js
function plugin_hello_convert(e) {
    const name = e.args[0];
    return <p class="greeting">こんにちは、{name} さん</p>;
}
```

## 有効・無効の切り替え

既定で有効です。plugin/pkript.inc.php の PKRIPT_JSX を 0 にすると無効になり、&lt; は比較演算子としてだけ働きます。

## 書ける形

### 要素と入れ子
```js
return <ul><li>a</li><li>b</li></ul>;
```

### 空要素
```js
return <br />;
```

閉じスラッシュが要ります。&lt;br&gt; だけでは閉じタグ待ちになります。

### フラグメント
複数の要素を、囲む要素なしで並べます。

```js
return <><p>a</p><p>b</p></>;
```

### 属性
文字列か {式} を書きます。

```js
const cls = e.opts["class"];
return <p class="fixed">x</p>;
return <p class={cls}>x</p>;
return <p title={42}>x</p>;
```

- className と書いても class になります。
- 値が null の属性は出力されません。
- {...obj} のスプレッド属性には対応していません。

### {} の中身
式なら何でも書けます。

```js
return <div><span>{1 + 1}</span></div>;
return <ul>{items.map((x) => <li>{x}</li>).join("")}</ul>;
```

null, false, 空文字列は何も出力しません。0 は "0" と出ます。

## エスケープの規則

ここがJSXの要点です。**文字列は必ずエスケープされ、JSX要素はされません。**

```js
const s = "<li>x</li>";        // ただの文字列
return <ul>{s}</ul>;           // <ul>&lt;li&gt;x&lt;/li&gt;</ul>
```

```js
const el = <li>x</li>;         // JSX要素
return <ul>{el}</ul>;          // <ul><li>x</li></ul>
```

wiki.convert() の戻り値もエスケープされません。Wiki記法から作ったHTMLをそのまま埋め込めます。

```js
return <div>{wiki.convert(e.body)}</div>;
```

### htmlsc は使わないでください
{} の中は自動でエスケープされます。htmlsc() を重ねると二重エスケープになります。

```js
return <p>{htmlsc("<b>")}</p>;    // &amp;lt;b&amp;gt; と出てしまう
return <p>{"<b>"}</p>;            // &lt;b&gt; が正しい
```

### 属性値も同じ
属性値に入れた文字列はエスケープされます。引用符を混ぜて属性を抜け出すことはできません。

```js
const c = "a\" onclick=\"alert(1)";
return <p class={c}>y</p>;        // class ごと落とされる
```

## サニタイザとの関係

JSXで書いたHTMLも、他の出力と同じようにサニタイザを通ります。JSXは記法を短くするだけで、出せるHTMLの範囲は変わりません。

| 書いたもの | 出力 |
| --- | --- |
| &lt;script&gt;alert(1)&lt;/script&gt; | タグごと削除 |
| &lt;p onclick="..."&gt; | onclick が削除 |
| &lt;p id="a"&gt; | id="pkript-a" に変換 |
| &lt;a href="javascript:..."&gt; | href が削除 |
| &lt;p style="position: fixed"&gt; | style が削除 |
| &lt;form&gt; | action が自Wikiに補完 |

詳しくは [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) のサニタイザの節を参照してください。

親ページ: [Pkript/文法](Pkript_%E6%96%87%E6%B3%95.md)

## 注意点

### 要素を文字列として切り貼りしない
JSX要素は、出力の直前まで内部の目印として持ち回ります。length や charAt の結果は、表示されるHTMLと一致しません。

```js
const el = <b>x</b>;
return el.length;        // 8 ではない
```

連結（+）と join() は安全です。

```js
return <b>a</b> + <i>b</i>;
return items.map((x) => <li>{x}</li>).join("");
```

### タグの間の空白は消えます
```js
return <ul>
  <li>a</li>
  <li>b</li>
</ul>;
```

上は &lt;ul&gt;&lt;li&gt;a&lt;/li&gt;&lt;li&gt;b&lt;/li&gt;&lt;/ul&gt; になります。要素の中の文字列に含まれる空白はそのまま残ります。

### HTMLコメントは書けません
&lt;!-- --&gt; は使えません。

### &lt; が比較演算子になる場合
直前のトークンで判断します。値や ) の後ろの &lt; は比較演算子、それ以外は JSX の始まりです。

```js
if (n < 10) { }          // 比較
return <p>x</p>;         // JSX
```

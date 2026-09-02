# Pkript/文法/JSX

HTML要素を式として記述する記法。文字列連結の代わりに使う。

## リファレンス

### 構文

```text
<タグ 属性="値">{式}</タグ>
```

- 設定定数 PKRIPT_JSX が 1（既定）の場合に利用できる。0 にすると &lt; は比較演算子としてだけ働く
- {} 内に渡された文字列は自動でHTMLエスケープされる
- JSX要素どうしを {} 内に埋め込んだ場合はエスケープされず、入れ子としてそのまま展開される
- 単一の親要素、またはフラグメント &lt;&gt; ... &lt;/&gt; で囲む

```js
function plugin_hello_convert(e) {
    const name = e.args[0];
    return <p class="greeting">こんにちは、{name} さん</p>;
}
```

### 書ける形

#### 要素と入れ子

```js
return <ul><li>a</li><li>b</li></ul>;
```

#### 空要素

```js
return <br />;
```

閉じスラッシュが要る。&lt;br&gt; だけでは閉じタグ待ちになる。

#### フラグメント

複数の要素を、囲む要素なしで並べる。

```js
return <><p>a</p><p>b</p></>;
```

#### 属性

文字列か {式} を書く。

```js
const cls = e.opts["class"];
return <p class="fixed">x</p>;
return <p class={cls}>x</p>;
return <p title={42}>x</p>;
```

- className と書いても class になる
- 値が null の属性は出力されない
- {...obj} のスプレッド属性には対応していない

#### {} の中身

式なら何でも書ける。

```js
return <div><span>{1 + 1}</span></div>;
return <ul>{items.map((x) => <li>{x}</li>).join("")}</ul>;
```

null, false, 空文字列は何も出力しない。0 は "0" と出る。

### エスケープの規則

ここがJSXの要点。**文字列は必ずエスケープされ、JSX要素はされない。**

```js
const s = "<li>x</li>";        // ただの文字列
return <ul>{s}</ul>;           // <ul>&lt;li&gt;x&lt;/li&gt;</ul>

const el = <li>x</li>;         // JSX要素
return <ul>{el}</ul>;          // <ul><li>x</li></ul>
```

wiki.convert() の戻り値もエスケープされない。Wiki記法から作ったHTMLをそのまま埋め込める。

```js
return <div>{wiki.convert(e.body)}</div>;
```

#### htmlsc は使わない

{} の中は自動でエスケープされる。htmlsc() を重ねると二重エスケープになる。

```js
return <p>{htmlsc("<b>")}</p>;    // &amp;lt;b&amp;gt; と出てしまう
return <p>{"<b>"}</p>;            // &lt;b&gt; が正しい
```

#### 属性値も同じ

属性値に入れた文字列はエスケープされる。引用符を混ぜて属性を抜け出すことはできない。

```js
const c = "a\" onclick=\"alert(1)";
return <p class={c}>y</p>;        // class ごと落とされる
```

### サニタイザとの関係

JSXで書いたHTMLも、他の出力と同じようにサニタイザを通る。JSXは記法を短くするだけで、出せるHTMLの範囲は変わらない。

| 書いたもの | 出力 |
| --- | --- |
| &lt;script&gt;alert(1)&lt;/script&gt; | タグごと削除 |
| &lt;p onclick="..."&gt; | onclick が削除 |
| &lt;p id="a"&gt; | id="pkript-a" に変換 |
| &lt;a href="javascript:..."&gt; | href が削除 |
| &lt;p style="position: fixed"&gt; | style が削除 |
| &lt;form&gt; | action が自Wikiに補完 |

詳しくは [Pkript/設定/サニタイザ](Pkript_%E8%A8%AD%E5%AE%9A_%E3%82%B5%E3%83%8B%E3%82%BF%E3%82%A4%E3%82%B6.md) を参照。

## 使用法

### 変数を埋め込んでコンポーネントを出力する

```js
function plugin_profile_convert(e) {
    const name = e.args[0] || "ゲスト";
    return (
        <div class="profile-box">
            <h3>プロフィール</h3>
            <p>名前: {name}</p>
        </div>
    );
}
```

### 配列から要素を組み立てる

```js
const items = e.args;
return <ul>{items.map((x) => <li>{x}</li>).join("")}</ul>;
```

## 注意点

### 要素を文字列として切り貼りしない

JSX要素は、出力の直前まで内部の目印として持ち回る。length や charAt の結果は、表示されるHTMLと一致しない。

```js
const el = <b>x</b>;
return el.length;        // 8 ではない
```

連結（+）と join() は安全。

```js
return <b>a</b> + <i>b</i>;
return items.map((x) => <li>{x}</li>).join("");
```

### タグの間の空白は消える

```js
return <ul>
  <li>a</li>
  <li>b</li>
</ul>;
```

上は &lt;ul&gt;&lt;li&gt;a&lt;/li&gt;&lt;li&gt;b&lt;/li&gt;&lt;/ul&gt; になる。要素の中の文字列に含まれる空白はそのまま残る。

### HTMLコメントは書けない

&lt;!-- --&gt; は使えない。

### &lt; が比較演算子になる場合

直前のトークンで判断する。値や ) の後ろの &lt; は比較演算子、それ以外は JSX の始まり。

```js
if (n < 10) { }          // 比較
return <p>x</p>;         // JSX
```

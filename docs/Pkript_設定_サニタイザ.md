# Pkript/設定/サニタイザ

スクリプトの出力HTMLに対する自動サニタイズの仕様。

## リファレンス

スクリプトの出力HTMLは自動でサニタイズされる。

- script / iframe 等の危険なタグは無効化される
- onclick 等のイベント属性は除去される
- href / src の javascript: と data: スキームは除去される
- class と id には自動で pkript- 接頭辞が付与される

### style属性で使えるCSS

プロパティのホワイトリストと値のパターン検査を組み合わせて検証する。

| 分類 | 許可するプロパティ |
| --- | --- |
| 色と文字 | color, background-color, font-*, text-*, line-height, letter-spacing, word-spacing, white-space, word-break, overflow-wrap, list-style* |
| ボックス | margin*, padding*, border*, box-shadow, box-sizing, width, height, min-*, max-*, display, vertical-align, opacity, visibility, overflow*, float, clear, cursor |
| テーブル | border-collapse, border-spacing, table-layout, caption-side, outline* |
| Flexbox | flex*, justify-*, align-*, order, gap, row-gap, column-gap |
| Grid | grid-template-*, grid-auto-*, grid-area, grid-column, grid-row, place-* |
| アニメーション | transition*, animation*, transform, transform-origin, will-change, filter |
| 配置 | position, z-index, top, right, bottom, left, inset |

```text
<div style="transform:rotate(3deg); transition:all 0.3s ease-in-out">
```

#### 配置プロパティ

position と z-index は使える。ただし次の2つの条件が付く。

- ブロック出力（#pkript, #スクリプト名）のみ。インライン出力（&amp;pkript(...);）ではこれらの宣言は除去される
- position の値は static / relative / absolute / sticky のみ。fixed は除去される

ブロック出力は、配置を含むためのラッパー要素に包まれる。絶対配置した子はそのラッパーを基準に置かれ、z-index をいくら上げてもラッパーの外には出ない。ページ本文の段落の中に入るインライン出力には包むものがないため、配置プロパティは渡らない。

```js
function plugin_badge_convert(e) {
    return <div style="position:relative">
        <span style="position:absolute; top:0; right:0; z-index:2">NEW</span>
        <p>{e.args[0]}</p>
    </div>;
}
```

長さの単位は px, em, rem, %, vw, vh などが使える。

禁止されるプロパティと値:

| 禁止対象 | 理由 |
| --- | --- |
| url(...) を含む値 | 外部リソース参照を防ぐため（background-image を含む） |
| expression(...) / javascript: / @import / \ / /* | スクリプト注入を防ぐため |

関数はホワイトリスト制: rgb / rgba / hsl / hsla、translate* / scale* / rotate* / skew* / matrix / perspective、cubic-bezier / steps、repeat / minmax / fit-content、blur 等のfilter関数のみ許可。引数も同じ規則で検査される。calc() は現在非対応。

規則に合わないプロパティは、そのプロパティのみ除去される（要素は残る）。

animation-name が参照するキーフレームはスクリプトから定義できない。&lt;style&gt; タグはサニタイザが除去するため、参照できるのはスキンが定義しているキーフレームのみ。

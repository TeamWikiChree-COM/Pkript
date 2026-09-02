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

```text
<div style="transform:rotate(3deg); transition:all 0.3s ease-in-out">
```

禁止されるプロパティと値:

| 禁止対象 | 理由 |
| --- | --- |
| url(...) を含む値 | 外部リソース参照を防ぐため（background-image を含む） |
| expression(...) / javascript: / @import / \ / /* | スクリプト注入を防ぐため |

関数はホワイトリスト制: rgb / rgba / hsl / hsla、translate* / scale* / rotate* / skew* / matrix / perspective、cubic-bezier / steps、repeat / minmax / fit-content、blur 等のfilter関数のみ許可。引数も同じ規則で検査される。calc() は現在非対応。

規則に合わないプロパティは、そのプロパティのみ除去される（要素は残る）。

animation-name が参照するキーフレームはスクリプトから定義できない。&lt;style&gt; タグはサニタイザが除去するため、参照できるのはスキンが定義しているキーフレームのみ。

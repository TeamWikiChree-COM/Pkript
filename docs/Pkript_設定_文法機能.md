# Pkript/設定/文法機能

JSX記法と正規表現リテラルの有効／無効を切り替える設定定数。

## リファレンス

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_JSX | int | 1 | JSX記法（&lt;tag&gt;{expr}&lt;/tag&gt;）を有効にするか |
| PKRIPT_REGEX | int | 1 | 正規表現リテラル（/pattern/flags）を有効にするか |

- PKRIPT_JSX を 0 にすると &lt; は比較演算子としてのみ動作する（[Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) 参照）
- PKRIPT_REGEX を 0 にすると / は除算としてのみ動作する（[Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md) 参照）

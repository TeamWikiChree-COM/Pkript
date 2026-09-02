# Pkript/文法

Pkript の言語仕様リファレンス。詳細は各ページへ。

## ページ一覧

- [Pkript/文法/変数](Pkript_%E6%96%87%E6%B3%95_%E5%A4%89%E6%95%B0.md): 変数宣言、スコープ、グローバル変数、再代入
- [Pkript/文法/データ型](Pkript_%E6%96%87%E6%B3%95_%E3%83%87%E3%83%BC%E3%82%BF%E5%9E%8B.md): プリミティブ型、オブジェクト、数値リテラル
- [Pkript/文法/演算子](Pkript_%E6%96%87%E6%B3%95_%E6%BC%94%E7%AE%97%E5%AD%90.md): 単項演算子と二項演算子、優先順位、??、?.、スプレッド
- [Pkript/文法/関数](Pkript_%E6%96%87%E6%B3%95_%E9%96%A2%E6%95%B0.md): 関数定義、アロー関数、エントリポイント、可変長引数
- [Pkript/文法/コンテキスト](Pkript_%E6%96%87%E6%B3%95_%E3%82%B3%E3%83%B3%E3%83%86%E3%82%AD%E3%82%B9%E3%83%88.md): e オブジェクト、e.user、リクエスト引数
- [Pkript/文法/制御構文](Pkript_%E6%96%87%E6%B3%95_%E5%88%B6%E5%BE%A1%E6%A7%8B%E6%96%87.md): if、switch、for、while、ラベル
- [Pkript/文法/テンプレートリテラル](Pkript_%E6%96%87%E6%B3%95_%E3%83%86%E3%83%B3%E3%83%97%E3%83%AC%E3%83%BC%E3%83%88%E3%83%AA%E3%83%86%E3%83%A9%E3%83%AB.md): 埋め込み式、バッククォート構文
- [Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md): HTMLタグ式、自動エスケープ、サニタイザとの関係
- [Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md): リテラル構文、フラグ、使えるメソッド、バックトラック制限
- [Pkript/文法/エラー処理](Pkript_%E6%96%87%E6%B3%95_%E3%82%A8%E3%83%A9%E3%83%BC%E5%87%A6%E7%90%86.md): try / catch、catch できない上限エラー
- [Pkript/文法/import](Pkript_%E6%96%87%E6%B3%95_import.md): モジュール読み込み、信頼度制限
- [Pkript/文法/フォーム](Pkript_%E6%96%87%E6%B3%95_%E3%83%95%E3%82%A9%E3%83%BC%E3%83%A0.md): 出力できるフォーム要素、送信値の受け取り

## 概要

JavaScript風の構文で書き、PHPには触れない。文の書き方はほぼJavaScriptと同じで、改行があれば文末のセミコロンを省略できる。

```js
const KEYWORDS = ["function", "return"];

function plugin_hello_convert(e) {
    const name = e.args[0] || "World";
    return "<p>Hello, " + htmlsc(name) + "!</p>";
}
```

| 項目 | 書けるもの | 詳細 |
| --- | --- | --- |
| 宣言 | var / let / const | [Pkript/文法/変数](Pkript_%E6%96%87%E6%B3%95_%E5%A4%89%E6%95%B0.md) |
| 型 | String, Number, Boolean, Null, Array, Object, RegExp | [Pkript/文法/データ型](Pkript_%E6%96%87%E6%B3%95_%E3%83%87%E3%83%BC%E3%82%BF%E5%9E%8B.md) |
| 関数 | function 宣言、アロー関数、クロージャ | [Pkript/文法/関数](Pkript_%E6%96%87%E6%B3%95_%E9%96%A2%E6%95%B0.md) |
| 制御 | if / else、switch、while、do..while、for、for..of、for..in、ラベル | [Pkript/文法/制御構文](Pkript_%E6%96%87%E6%B3%95_%E5%88%B6%E5%BE%A1%E6%A7%8B%E6%96%87.md) |
| 文字列 | "..." / '...' / `...${式}...` | [Pkript/文法/テンプレートリテラル](Pkript_%E6%96%87%E6%B3%95_%E3%83%86%E3%83%B3%E3%83%97%E3%83%AC%E3%83%BC%E3%83%88%E3%83%AA%E3%83%86%E3%83%A9%E3%83%AB.md) |
| HTML出力 | 文字列連結、JSX記法 | [Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) |
| 例外 | try / catch（throw と finally は使えない） | [Pkript/文法/エラー処理](Pkript_%E6%96%87%E6%B3%95_%E3%82%A8%E3%83%A9%E3%83%BC%E5%87%A6%E7%90%86.md) |
| 分割 | import "スクリプト名"; | [Pkript/文法/import](Pkript_%E6%96%87%E6%B3%95_import.md) |

エントリポイントは plugin_◯◯_convert / inline / action の3種類で、第1引数にコンテキスト e を受け取る。

## 関連

- [Pkript/API](Pkript_API.md) - 組み込み関数とオブジェクト
- [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) - 文法機能の有効／無効、上限値
- [Pkript/サンプル](Pkript_%E3%82%B5%E3%83%B3%E3%83%97%E3%83%AB.md) - 実用スクリプト集
- [Pkript/エラー一覧](Pkript_%E3%82%A8%E3%83%A9%E3%83%BC%E4%B8%80%E8%A6%A7.md) - エラーメッセージと対処法
